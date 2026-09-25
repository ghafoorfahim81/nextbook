<?php

namespace Tests\Feature\Inventory;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Models\Administration\CustomerGroup;
use App\Models\Inventory\DiscountRule;
use App\Models\Role;
use App\Models\User;
use App\Services\DiscountRuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Automatic sale discounts. The rule that wins is always the most specific one,
 * and nothing ever stacks.
 */
class DiscountRuleTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
    }

    private function rule(array $attributes = []): DiscountRule
    {
        return DiscountRule::factory()->create(array_merge([
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
            'scope' => DiscountScope::ITEM->value,
            'scope_id' => $this->ctx['item']->id,
        ], $attributes));
    }

    /** A signed-in user of the same company who holds no permissions at all. */
    private function userWithoutPermissions(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'no-access', 'guard_name' => 'web'],
            ['slug' => 'no-access'],
        );

        return tap(User::factory()->create([
            'preferences' => User::DEFAULT_PREFERENCES,
            'company_id' => $this->ctx['company']->id,
            'branch_id' => $this->ctx['branch']->id,
        ]), fn (User $user) => $user->assignRole($role));
    }

    private function resolver(): DiscountRuleResolver
    {
        $resolver = app(DiscountRuleResolver::class);
        $resolver->flush();

        return $resolver;
    }

    public function test_a_percentage_rule_takes_its_share_of_the_line_total(): void
    {
        $this->rule(['discount_type' => DiscountType::PERCENTAGE->value, 'value' => 10]);

        // 4 x 250 = 1000, less 10%.
        $this->assertEqualsWithDelta(
            100.0,
            $this->resolver()->discountFor($this->ctx['item']->id, 4, 250, branchId: $this->ctx['branch']->id),
            0.0001,
        );
    }

    public function test_a_flat_rule_comes_off_the_line_once_not_per_unit(): void
    {
        $this->rule(['discount_type' => DiscountType::CURRENCY->value, 'value' => 30]);

        $this->assertEqualsWithDelta(
            30.0,
            $this->resolver()->discountFor($this->ctx['item']->id, 4, 250, branchId: $this->ctx['branch']->id),
            0.0001,
        );
    }

    public function test_a_discount_can_never_exceed_the_line_it_comes_off(): void
    {
        $this->rule(['discount_type' => DiscountType::CURRENCY->value, 'value' => 9999]);

        $this->assertEqualsWithDelta(
            100.0,
            $this->resolver()->discountFor($this->ctx['item']->id, 1, 100, branchId: $this->ctx['branch']->id),
            0.0001,
        );
    }

    public function test_an_item_rule_beats_a_category_rule_on_the_same_line(): void
    {
        $item = $this->ctx['item'];
        $item->update(['category_id' => $this->ctx['category']->id ?? null]);

        $this->rule([
            'scope' => DiscountScope::CATEGORY->value,
            'scope_id' => $item->category_id,
            'value' => 50,
        ]);
        $this->rule(['scope' => DiscountScope::ITEM->value, 'scope_id' => $item->id, 'value' => 5]);

        // The item rule wins even though the category rule is worth more.
        $winner = $this->resolver()->resolve($item->id, 1, branchId: $this->ctx['branch']->id);

        $this->assertSame(DiscountScope::ITEM, $winner?->scope);
    }

    public function test_a_customer_group_rule_beats_the_same_scope_without_one(): void
    {
        $wholesale = CustomerGroup::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name_en' => 'Wholesale',
        ]);
        $wholesaleBuyer = $this->ctx['customer_ledger'];
        $wholesaleBuyer->update(['group_id' => $wholesale->id]);

        $this->rule(['value' => 5]);
        $this->rule(['value' => 20, 'customer_group_id' => $wholesale->id]);

        $forWholesale = $this->resolver()->resolve(
            $this->ctx['item']->id, 1, ledgerId: $wholesaleBuyer->id, branchId: $this->ctx['branch']->id,
        );
        $forWalkIn = $this->resolver()->resolve(
            $this->ctx['item']->id, 1, branchId: $this->ctx['branch']->id,
        );

        $this->assertEquals(20.0, (float) $forWholesale?->value);
        $this->assertEquals(5.0, (float) $forWalkIn?->value);
    }

    public function test_a_group_rule_does_not_reach_a_customer_in_another_group(): void
    {
        $wholesale = CustomerGroup::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name_en' => 'Wholesale',
        ]);
        $retail = CustomerGroup::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name_en' => 'Retail',
        ]);

        $retailBuyer = $this->ctx['customer_ledger'];
        $retailBuyer->update(['group_id' => $retail->id]);

        $this->rule(['value' => 20, 'customer_group_id' => $wholesale->id]);

        $this->assertNull($this->resolver()->resolve(
            $this->ctx['item']->id, 1, ledgerId: $retailBuyer->id, branchId: $this->ctx['branch']->id,
        ));
    }

    public function test_a_rule_outside_its_dates_does_not_fire(): void
    {
        $this->rule(['starts_at' => '2026-01-01', 'ends_at' => '2026-01-31']);

        $inside = $this->resolver()->resolve(
            $this->ctx['item']->id, 1, branchId: $this->ctx['branch']->id, onDate: '2026-01-15',
        );
        $after = $this->resolver()->resolve(
            $this->ctx['item']->id, 1, branchId: $this->ctx['branch']->id, onDate: '2026-02-01',
        );

        $this->assertNotNull($inside);
        $this->assertNull($after);
    }

    public function test_a_bulk_rule_waits_for_its_minimum_quantity(): void
    {
        $this->rule(['min_quantity' => 10]);

        $this->assertNull($this->resolver()->resolve($this->ctx['item']->id, 9, branchId: $this->ctx['branch']->id));
        $this->assertNotNull($this->resolver()->resolve($this->ctx['item']->id, 10, branchId: $this->ctx['branch']->id));
    }

    /**
     * Priority is a switch, not a number. It only ever decided which of two
     * equally specific rules wins, so there was nothing for "4" or "5" to mean
     * beyond "more than the other one".
     */
    public function test_priority_breaks_a_tie_between_two_equally_specific_rules(): void
    {
        $this->rule(['name' => 'Ordinary', 'value' => 30, 'is_priority' => false]);
        $this->rule(['name' => 'Wins ties', 'value' => 5, 'is_priority' => true]);

        $winner = $this->resolver()->resolve(
            $this->ctx['item']->id, 1, branchId: $this->ctx['branch']->id,
        );

        // Same scope, same group: the switched-on rule wins even though the
        // other one is worth six times more.
        $this->assertSame('Wins ties', $winner?->name);
    }

    public function test_without_priority_the_larger_discount_wins_a_tie(): void
    {
        $this->rule(['name' => 'Small', 'value' => 5]);
        $this->rule(['name' => 'Large', 'value' => 30]);

        $winner = $this->resolver()->resolve(
            $this->ctx['item']->id, 1, branchId: $this->ctx['branch']->id,
        );

        $this->assertSame('Large', $winner?->name);
    }

    /** A rule with no group applies to everyone, and the screen says so. */
    public function test_a_rule_without_a_customer_group_reads_as_all_customers(): void
    {
        $this->rule(['customer_group_id' => null]);

        $this->get(route('discount-rules.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('discountRules.data.0.customer_group_name', 'All customers'));
    }

    public function test_a_rule_with_a_group_still_names_that_group(): void
    {
        $wholesale = CustomerGroup::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name_en' => 'Wholesale',
        ]);

        $this->rule(['customer_group_id' => $wholesale->id]);

        $this->get(route('discount-rules.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('discountRules.data.0.customer_group_name', 'Wholesale'));
    }

    public function test_an_inactive_rule_is_ignored(): void
    {
        $this->rule(['is_active' => false]);

        $this->assertNull($this->resolver()->resolve($this->ctx['item']->id, 1, branchId: $this->ctx['branch']->id));
    }

    /** The backstop, for a caller that posts no discount field at all. */
    public function test_a_sale_line_with_no_discount_field_falls_back_to_the_rule(): void
    {
        $this->rule(['discount_type' => DiscountType::PERCENTAGE->value, 'value' => 10]);

        $this->post(route('sales.store'), $this->salePayload(itemDiscount: null));

        $this->assertEqualsWithDelta(100.0, (float) \App\Models\Sale\SaleItem::query()->value('discount'), 0.0001);
    }

    public function test_a_typed_line_discount_overrules_the_rule(): void
    {
        $this->rule(['discount_type' => DiscountType::PERCENTAGE->value, 'value' => 10]);

        $this->post(route('sales.store'), $this->salePayload(itemDiscount: 7));

        $this->assertEqualsWithDelta(7.0, (float) \App\Models\Sale\SaleItem::query()->value('discount'), 0.0001);
    }

    /**
     * The sale form fills the discount box in and the salesperson can take it
     * back out again. Every way an emptied box can reach the controller has to
     * mean zero, or the rule quietly puts its own figure back and the customer
     * is given a discount nobody agreed to.
     *
     * '' matters as much as 0 here: ConvertEmptyStringsToNull rewrites it to
     * null in middleware, and the validation rule is nullable, so a cleared box
     * arrives as null rather than as the empty string that was typed.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('clearedDiscountProvider')]
    public function test_a_line_sent_with_an_emptied_discount_keeps_the_zero(mixed $posted): void
    {
        $this->rule(['discount_type' => DiscountType::PERCENTAGE->value, 'value' => 10]);

        $payload = $this->salePayload(itemDiscount: null);
        $payload['item_list'][0]['item_discount'] = $posted;

        $this->post(route('sales.store'), $payload);

        $this->assertEqualsWithDelta(
            0.0,
            (float) \App\Models\Sale\SaleItem::query()->value('discount'),
            0.0001,
            'posted '.var_export($posted, true),
        );
    }

    public static function clearedDiscountProvider(): array
    {
        return [
            'integer zero' => [0],
            'string zero' => ['0'],
            'emptied box' => [''],
            'explicit null' => [null],
        ];
    }

    /**
     * The sale form asks for the shortlist once per item and then re-picks from
     * it locally as the quantity changes, so the response must carry every
     * candidate — best first, minimum-quantity gate still open.
     */
    public function test_the_sale_form_is_handed_every_candidate_rule_best_first(): void
    {
        $this->rule(['name' => 'Everyday', 'value' => 5, 'is_priority' => false]);
        $this->rule(['name' => 'Ten or more', 'value' => 15, 'is_priority' => true, 'min_quantity' => 10]);

        $response = $this->getJson(route('discount-rules.for-items', [
            'item_ids' => [$this->ctx['item']->id],
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-10',
        ]))->assertOk();

        $candidates = $response->json('data.'.$this->ctx['item']->id);

        $this->assertCount(2, $candidates);
        $this->assertSame('Ten or more', $candidates[0]['name']);
        $this->assertEquals(10.0, $candidates[0]['min_quantity']);
        $this->assertSame('Everyday', $candidates[1]['name']);
        $this->assertNull($candidates[1]['min_quantity']);
    }

    /**
     * Taking the first candidate the line's quantity reaches has to land on the
     * same rule the server would have chosen — that equivalence is the whole
     * reason the form is allowed to pick locally.
     */
    public function test_picking_the_first_candidate_the_quantity_reaches_matches_the_resolver(): void
    {
        $this->rule(['name' => 'Everyday', 'value' => 5]);
        $this->rule(['name' => 'Ten or more', 'value' => 15, 'is_priority' => true, 'min_quantity' => 10]);

        $candidates = $this->resolver()->candidatesFor(
            $this->ctx['item']->id, branchId: $this->ctx['branch']->id,
        );

        foreach ([1, 9, 10, 40] as $quantity) {
            $picked = $candidates->first(
                fn ($rule) => $rule->min_quantity === null || $quantity >= (float) $rule->min_quantity,
            );
            $resolved = $this->resolver()->resolve(
                $this->ctx['item']->id, $quantity, branchId: $this->ctx['branch']->id,
            );

            $this->assertSame($resolved?->id, $picked?->id, "quantity {$quantity}");
        }
    }

    public function test_the_candidate_list_is_closed_to_someone_who_cannot_write_a_sale(): void
    {
        $this->rule();

        $this->actingAs($this->userWithoutPermissions());

        $this->getJson(route('discount-rules.for-items', [
            'item_ids' => [$this->ctx['item']->id],
        ]))->assertForbidden();
    }

    public function test_the_crud_screen_lists_rules_and_offers_its_pickers(): void
    {
        $this->rule(['name' => 'Ramadan Promo']);

        $this->get(route('discount-rules.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventories/DiscountRules/Index')
                ->has('discountRules.data', 1)
                ->where('discountRules.data.0.name', 'Ramadan Promo')
                ->has('options.scopes')
                ->has('options.items')
                ->has('options.customerGroups'));
    }

    /**
     * DataTable renders its footer from items.meta.from without a null guard,
     * so a raw paginator (which has no `meta`) blanks the whole page.
     */
    public function test_the_list_payload_carries_the_pagination_meta_the_table_needs(): void
    {
        $this->rule();

        $this->get(route('discount-rules.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('discountRules.meta.from')
                ->has('discountRules.meta.to')
                ->has('discountRules.meta.total')
                ->has('discountRules.meta.current_page')
                ->has('discountRules.meta.per_page')
                ->has('discountRules.meta.last_page'));
    }

    public function test_each_row_carries_both_its_labels_and_the_raw_values_the_edit_form_reloads(): void
    {
        $this->rule([
            'name' => 'Bulk Deal',
            'discount_type' => DiscountType::PERCENTAGE->value,
            'value' => 12.5,
        ]);

        $this->get(route('discount-rules.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Labels the table prints.
                ->where('discountRules.data.0.value_label', '12.5%')
                ->where('discountRules.data.0.target_name', $this->ctx['item']->name)
                // The literal, not __(): the labels are assembled server-side,
                // so a missing PHP lang file must fail here.
                ->where('discountRules.data.0.window_label', 'Always')
                ->has('discountRules.data.0.scope_label')
                // Raw values the modal loads back into its form.
                ->where('discountRules.data.0.scope', DiscountScope::ITEM->value)
                ->where('discountRules.data.0.scope_id', $this->ctx['item']->id)
                ->where('discountRules.data.0.is_active', true));
    }

    public function test_a_rule_can_be_created_edited_and_deleted_from_the_screen(): void
    {
        $this->post(route('discount-rules.store'), [
            'name' => 'Winter Sale',
            'scope' => DiscountScope::ITEM->value,
            'scope_id' => $this->ctx['item']->id,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'value' => 15,
            'is_active' => true,
            'show_on_invoice' => true,
        ])->assertRedirect();

        $rule = DiscountRule::query()->firstOrFail();
        $this->assertSame('Winter Sale', $rule->name);

        $this->patch(route('discount-rules.update', $rule->id), [
            'name' => 'Winter Sale',
            'scope' => DiscountScope::ITEM->value,
            'scope_id' => $this->ctx['item']->id,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'value' => 25,
            'is_active' => true,
            'show_on_invoice' => false,
        ])->assertRedirect();

        $this->assertEquals(25.0, (float) $rule->fresh()->value);
        $this->assertFalse($rule->fresh()->show_on_invoice);

        $this->delete(route('discount-rules.destroy', $rule->id))->assertRedirect();
        $this->assertSoftDeleted('discount_rules', ['id' => $rule->id]);
    }

    public function test_a_targeted_scope_is_rejected_without_a_target(): void
    {
        $this->post(route('discount-rules.store'), [
            'name' => 'Broken',
            'scope' => DiscountScope::CATEGORY->value,
            'scope_id' => null,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'value' => 10,
        ])->assertSessionHasErrors('scope_id');
    }

    public function test_a_percentage_over_one_hundred_is_rejected(): void
    {
        $this->post(route('discount-rules.store'), [
            'name' => 'Too generous',
            'scope' => DiscountScope::ALL->value,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'value' => 140,
        ])->assertSessionHasErrors('value');
    }

    /** 100% is a giveaway, not an overcharge, so the cap includes it. */
    public function test_a_percentage_of_exactly_one_hundred_is_allowed(): void
    {
        $this->post(route('discount-rules.store'), [
            'name' => 'On the house',
            'scope' => DiscountScope::ALL->value,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'value' => 100,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(100.0, (float) DiscountRule::query()->firstOrFail()->value);
    }

    /** The cap is on percentages only; a flat amount can exceed 100 currency units. */
    public function test_a_currency_rule_may_exceed_one_hundred(): void
    {
        $this->post(route('discount-rules.store'), [
            'name' => 'Flat 500 off',
            'scope' => DiscountScope::ALL->value,
            'discount_type' => DiscountType::CURRENCY->value,
            'value' => 500,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(500.0, (float) DiscountRule::query()->firstOrFail()->value);
    }

    /**
     * Deleting a rule is final. There is no restore endpoint and the rule never
     * shows up in Trash, so the screen must not offer an undo it cannot honour.
     */
    public function test_a_deleted_rule_cannot_be_restored(): void
    {
        $rule = DiscountRule::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'scope' => DiscountScope::ALL->value,
            'scope_id' => null,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'value' => 10,
        ]);

        $this->delete(route('discount-rules.destroy', $rule->id))->assertRedirect();
        $this->assertSoftDeleted('discount_rules', ['id' => $rule->id]);

        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('discount-rules.restore'),
            'A discount rule delete is not reversible; adding a restore route would make the Undo toast the right call instead.'
        );

        // Nor does the rule turn up in Trash, where it could be restored by hand.
        $this->get(route('deleted-records.index'))
            ->assertInertia(fn ($page) => $page
                ->where('records.data', fn ($records) => collect($records)
                    ->doesntContain(fn ($record) => ($record['record_id'] ?? null) === $rule->id)));
    }

    /**
     * 4 x 250 = 1000 so a 10% rule is worth exactly 100.
     *
     * A null $itemDiscount leaves the key off the line entirely, which is how a
     * caller that is not the sale form posts.
     */
    private function salePayload(?float $itemDiscount): array
    {
        app(\App\Services\StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => \App\Enums\StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 50,
            'source' => \App\Enums\StockSourceType::PURCHASE->value,
            'unit_cost' => 10,
            'status' => \App\Enums\StockStatus::POSTED->value,
            'batch' => null,
            'expire_date' => null,
            'date' => '2026-03-01',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'discount-rule-test',
            'reference_id' => $this->ctx['item']->id,
        ]);

        return [
            'number' => 8801,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-10',
            'transaction_total' => 1000,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [array_filter([
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => 4,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 250,
                'item_discount' => $itemDiscount,
                'free' => 0,
                'tax' => 0,
            ], fn ($value, $key) => $key !== 'item_discount' || $value !== null, ARRAY_FILTER_USE_BOTH)],
        ];
    }
}

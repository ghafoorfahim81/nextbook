<?php

namespace Tests\Feature\Inventory;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Models\Administration\CustomerGroup;
use App\Models\Inventory\DiscountRule;
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

    public function test_an_inactive_rule_is_ignored(): void
    {
        $this->rule(['is_active' => false]);

        $this->assertNull($this->resolver()->resolve($this->ctx['item']->id, 1, branchId: $this->ctx['branch']->id));
    }

    public function test_a_sale_line_picks_up_the_rule_when_no_discount_is_typed(): void
    {
        $this->rule(['discount_type' => DiscountType::PERCENTAGE->value, 'value' => 10]);

        $this->post(route('sales.store'), $this->salePayload(itemDiscount: 0));

        $this->assertEqualsWithDelta(100.0, (float) \App\Models\Sale\SaleItem::query()->value('discount'), 0.0001);
    }

    public function test_a_typed_line_discount_overrules_the_rule(): void
    {
        $this->rule(['discount_type' => DiscountType::PERCENTAGE->value, 'value' => 10]);

        $this->post(route('sales.store'), $this->salePayload(itemDiscount: 7));

        $this->assertEqualsWithDelta(7.0, (float) \App\Models\Sale\SaleItem::query()->value('discount'), 0.0001);
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

    /** 4 x 250 = 1000 so a 10% rule is worth exactly 100. */
    private function salePayload(float $itemDiscount): array
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
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => 4,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 250,
                'item_discount' => $itemDiscount,
                'free' => 0,
                'tax' => 0,
            ]],
        ];
    }
}

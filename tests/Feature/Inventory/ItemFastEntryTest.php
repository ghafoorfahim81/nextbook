<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockSourceType;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Fast Entry creates catalogue rows in bulk. It builds the item directly rather
 * than through the item form, so it still has to give each one its default
 * variant — variant_id must never be null downstream.
 */
class ItemFastEntryTest extends TestCase
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

    public function test_the_grid_is_told_which_columns_this_trade_needs(): void
    {
        $this->get(route('item.fast.entry'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventories/Items/FasEntry')
                ->has('maxCode')
                // The batch / expiry columns are decided from this, so the page
                // cannot render correctly without it.
                ->has('businessProfile.defaults.is_batch_tracked')
                ->has('businessProfile.defaults.is_expiry_tracked')
            );
    }

    public function test_a_non_numeric_existing_code_does_not_break_the_next_code(): void
    {
        // Codes are text: an import or a hand-typed "ITEM-2024/A" is legal, and
        // casting the whole column to integer made PostgreSQL throw on it.
        Item::factory()->create(['code' => 'ITEM-2024/A', 'branch_id' => $this->ctx['branch']->id]);
        Item::factory()->create(['code' => '0042', 'branch_id' => $this->ctx['branch']->id]);

        $this->get(route('item.fast.entry'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('maxCode', 43));
    }

    public function test_it_creates_items_each_with_a_default_variant(): void
    {
        $this->post(route('item.fast.store'), [
            'items' => [
                ['name' => 'Bottled Water 500ml', 'code' => '7001', 'barcode' => '7000001', 'measure_id' => $this->ctx['unit_measure']->id, 'purchase_price' => 8, 'sale_price' => 12],
                ['name' => 'Sponge Cake', 'code' => '7002', 'measure_id' => $this->ctx['unit_measure']->id, 'purchase_price' => 40, 'sale_price' => 60],
            ],
        ])->assertSessionHasNoErrors();

        $water = Item::where('code', '7001')->firstOrFail();
        $this->assertCount(1, $water->variants);

        $variant = $water->variants->first();
        $this->assertTrue($variant->is_default);
        $this->assertSame('7000001', $variant->barcode);
        $this->assertSame('8.0000', (string) $variant->purchase_price);

        // The legacy item columns stay mirrored from the default variant.
        $this->assertSame('7000001', $water->barcode);
        $this->assertSame('12.00', (string) $water->sale_price);

        $this->assertCount(1, Item::where('code', '7002')->firstOrFail()->variants);
    }

    public function test_it_posts_an_opening_when_quantity_and_warehouse_are_given(): void
    {
        $this->post(route('item.fast.store'), [
            'items' => [
                [
                    'name' => 'Cooking Oil 1L',
                    'code' => '7003',
                    'measure_id' => $this->ctx['unit_measure']->id,
                    'purchase_price' => 90,
                    'sale_price' => 110,
                    'quantity' => 10,
                    'warehouse_id' => $this->ctx['warehouse']->id,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $item = Item::where('code', '7003')->firstOrFail();
        $this->assertCount(1, $item->variants);

        $this->assertEqualsWithDelta(
            10.0,
            (float) StockBalance::where('item_id', $item->id)->sum('quantity'),
            0.0001,
        );

        $this->assertTrue(
            StockMovement::where('item_id', $item->id)
                ->where('source', StockSourceType::OPENING->value)
                ->exists()
        );

        // Every other entry point sets a variant, so the opening movement must too.
        $this->assertSame(
            $item->variants->first()->id,
            StockMovement::where('item_id', $item->id)->value('variant_id'),
        );
    }

    public function test_final_cost_drives_the_opening_movement_and_its_voucher(): void
    {
        $this->post(route('item.fast.store'), [
            'items' => [
                [
                    'name' => 'Imported Rice 10kg',
                    'code' => '7004',
                    'measure_id' => $this->ctx['unit_measure']->id,
                    'purchase_price' => 100,
                    // Landed cost: the purchase price plus freight and clearing.
                    'cost' => 130,
                    'quantity' => 5,
                    'warehouse_id' => $this->ctx['warehouse']->id,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $item = Item::where('code', '7004')->firstOrFail();

        $this->assertSame(130.0, (float) $item->cost);
        $this->assertSame(
            130.0,
            (float) StockMovement::where('item_id', $item->id)->value('unit_cost'),
        );

        // 5 × 130 lands on both sides of the opening voucher.
        $this->assertEqualsWithDelta(
            650.0,
            (float) TransactionLine::whereIn(
                'transaction_id',
                Transaction::where('reference_id', $item->id)->pluck('id')
            )->sum('debit'),
            0.0001,
        );
    }

    public function test_the_purchase_price_is_used_when_no_final_cost_is_given(): void
    {
        $this->post(route('item.fast.store'), [
            'items' => [
                [
                    'name' => 'Plain Sugar 1kg',
                    'code' => '7005',
                    'measure_id' => $this->ctx['unit_measure']->id,
                    'purchase_price' => 55,
                    'quantity' => 3,
                    'warehouse_id' => $this->ctx['warehouse']->id,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $item = Item::where('code', '7005')->firstOrFail();

        $this->assertSame(
            55.0,
            (float) StockMovement::where('item_id', $item->id)->value('unit_cost'),
        );
    }

    public function test_an_opening_quantity_without_a_warehouse_is_rejected(): void
    {
        $this->post(route('item.fast.store'), [
            'items' => [
                [
                    'name' => 'Nowhere To Put It',
                    'code' => '7006',
                    'measure_id' => $this->ctx['unit_measure']->id,
                    'quantity' => 4,
                ],
            ],
        ])->assertSessionHasErrors('items.0.warehouse_id');

        $this->assertDatabaseMissing('items', ['code' => '7006']);
    }

    public function test_two_rows_naming_the_same_item_are_rejected(): void
    {
        $this->post(route('item.fast.store'), [
            'items' => [
                ['name' => 'Duplicate Me', 'code' => '7007', 'measure_id' => $this->ctx['unit_measure']->id],
                ['name' => 'Duplicate Me', 'code' => '7008', 'measure_id' => $this->ctx['unit_measure']->id],
            ],
        ])->assertSessionHasErrors('items.0.name');

        $this->assertDatabaseMissing('items', ['code' => '7007']);
    }

    public function test_an_opening_worth_nothing_posts_stock_but_no_voucher(): void
    {
        $this->post(route('item.fast.store'), [
            'items' => [
                [
                    'name' => 'Free Sample',
                    'code' => '7009',
                    'measure_id' => $this->ctx['unit_measure']->id,
                    'quantity' => 2,
                    'warehouse_id' => $this->ctx['warehouse']->id,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $item = Item::where('code', '7009')->firstOrFail();

        $this->assertTrue(StockMovement::where('item_id', $item->id)->exists());
        $this->assertFalse(Transaction::where('reference_id', $item->id)->exists());
    }
}

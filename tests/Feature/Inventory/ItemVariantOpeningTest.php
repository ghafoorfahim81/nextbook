<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Opening stock for a multi-variant item is placed against the chosen variant.
 *
 * The form keys each opening row to a variant by its position in the variant
 * grid (variant_index); the controller resolves that to a variant_id and hands
 * it to StockService, which now carries it onto the movement and the balance
 * bucket. A single-variant item still gets a NULL bucket, unchanged.
 */
class ItemVariantOpeningTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dell Latitude 5540',
            'code' => '9001',
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'asset_account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'income_account_id' => $this->ctx['accounts']['product-income']->id,
            'cost_account_id' => $this->ctx['accounts']['cost-of-goods-sold']->id,
            'sale_price' => 900,
            'purchase_price' => 700,
            'is_batch_tracked' => false,
            'is_expiry_tracked' => false,
            'variants' => [
                ['attributes' => ['ram' => '8 GB'], 'sku' => 'DL-8', 'purchase_price' => 700, 'is_default' => true, 'sort_order' => 0],
                ['attributes' => ['ram' => '16 GB'], 'sku' => 'DL-16', 'purchase_price' => 900, 'sort_order' => 1],
            ],
        ], $overrides);
    }

    public function test_each_opening_lands_in_its_own_variant_bucket(): void
    {
        $warehouseId = $this->ctx['warehouse']->id;

        $this->post(route('items.store'), $this->payload([
            'openings' => [
                ['variant_index' => 0, 'quantity' => 12, 'unit_price' => 700, 'warehouse_id' => $warehouseId],
                ['variant_index' => 1, 'quantity' => 8, 'unit_price' => 900, 'warehouse_id' => $warehouseId],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $item = Item::where('code', '9001')->firstOrFail();
        $bySku = $item->variants->keyBy('sku');
        $v8 = $bySku['DL-8'];
        $v16 = $bySku['DL-16'];

        $balances = StockBalance::where('item_id', $item->id)->get();
        $this->assertCount(2, $balances);

        $this->assertEqualsWithDelta(
            12.0,
            (float) $balances->firstWhere('variant_id', $v8->id)?->quantity,
            0.0001,
        );
        $this->assertEqualsWithDelta(
            8.0,
            (float) $balances->firstWhere('variant_id', $v16->id)?->quantity,
            0.0001,
        );

        $movements = StockMovement::where('item_id', $item->id)
            ->where('source', StockSourceType::OPENING->value)
            ->get();
        $this->assertEqualsCanonicalizing(
            [$v8->id, $v16->id],
            $movements->pluck('variant_id')->all(),
        );

        // One opening voucher for the whole item: 12*700 + 8*900 = 15,600.
        $this->assertEqualsWithDelta(15_600.0, $this->openingVoucherValue($item), 0.01);
    }

    public function test_a_multi_variant_item_rejects_an_opening_with_no_variant(): void
    {
        $this->post(route('items.store'), $this->payload([
            'openings' => [
                ['quantity' => 12, 'unit_price' => 700, 'warehouse_id' => $this->ctx['warehouse']->id],
            ],
        ]))->assertSessionHasErrors('openings.0.variant_index');

        $this->assertSame(0, Item::where('code', '9001')->count());
    }

    public function test_a_single_variant_item_keeps_a_null_bucket(): void
    {
        $this->post(route('items.store'), $this->payload([
            'variants' => [
                ['attributes' => [], 'sku' => 'DL-ONLY', 'purchase_price' => 700, 'is_default' => true, 'sort_order' => 0],
            ],
            'openings' => [
                ['quantity' => 5, 'unit_price' => 700, 'warehouse_id' => $this->ctx['warehouse']->id],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $item = Item::where('code', '9001')->firstOrFail();
        $balance = StockBalance::where('item_id', $item->id)->firstOrFail();

        $this->assertNull($balance->variant_id);
        $this->assertEqualsWithDelta(5.0, (float) $balance->quantity, 0.0001);

        $movement = StockMovement::where('item_id', $item->id)
            ->where('source', StockSourceType::OPENING->value)
            ->firstOrFail();
        $this->assertNull($movement->variant_id);
        $this->assertSame(StockMovementType::IN->value, $movement->movement_type->value);
    }

    private function openingVoucherValue(Item $item): float
    {
        return (float) \App\Models\Transaction\Transaction::query()
            ->whereIn('reference_type', ['item', Item::class])
            ->where('reference_id', $item->id)
            ->join('transaction_lines', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->sum('transaction_lines.debit');
    }
}

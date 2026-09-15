<?php

namespace Tests\Feature\Inventory;

use App\Enums\CostingMethod;
use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Account\Account;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockAdjustmentItem;
use App\Services\ItemVariantService;
use App\Services\StockAdjustmentService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Stock adjustment under the variant structure: every line records which
 * variant moved, and the cost credited to the GL has to match the layers
 * StockService will actually consume.
 */
class StockAdjustmentVariantTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);

        // The offset accounts an adjustment posts against are not part of the
        // shared ERP context.
        foreach ([
            ['Inventory Shrinkage & Wastage', '9040', 'inventory-shrinkage-and-wastage'],
            ['Inventory Adjustments', '9050', 'inventory-adjustments'],
        ] as [$name, $number, $slug]) {
            Account::factory()->create([
                'branch_id' => $this->ctx['branch']->id,
                'name' => $name,
                'number' => $number,
                'slug' => $slug,
                'account_type_id' => $this->ctx['account_types']['expense']->id,
                'is_main' => true,
                'is_active' => true,
            ]);
        }
    }

    /** Put stock on the shelf at a known cost, the way a receipt would. */
    private function receive(Item $item, float $quantity, float $unitCost, ?string $batch = null, ?string $variantId = null): void
    {
        app(StockService::class)->post([
            'item_id' => $item->id,
            'variant_id' => $variantId,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $item->unit_measure_id,
            'quantity' => $quantity,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => $unitCost,
            'status' => StockStatus::POSTED->value,
            'batch' => $batch,
            'expire_date' => null,
            'date' => now()->toDateString(),
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $item->branch_id,
        ]);
    }

    private function adjust(Item $item, array $overrides = []): StockAdjustment
    {
        return app(StockAdjustmentService::class)->create(array_merge([
            'date' => now()->toDateString(),
            'reason' => StockAdjustmentReason::cases()[0]->value,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'items' => [[
                'item_id' => $item->id,
                'unit_measure_id' => $item->unit_measure_id,
                'quantity' => 1,
            ]],
        ], $overrides));
    }

    public function test_a_line_records_the_variant_even_when_the_form_sends_none(): void
    {
        $item = $this->ctx['item'];
        $variant = app(ItemVariantService::class)->ensureDefault($item);
        $this->receive($item, 10, 20);

        $adjustment = $this->adjust($item);

        // The warehouse-aware picker does not resolve a variant yet, so the
        // server has to fill in the default rather than write a null line.
        $this->assertSame(
            $variant->id,
            StockAdjustmentItem::where('stock_adjustment_id', $adjustment->id)->value('variant_id'),
        );
    }

    public function test_a_line_keeps_the_variant_the_caller_chose(): void
    {
        $item = Item::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'asset_account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'code' => 'ADJ-VAR',
        ]);

        $variants = app(ItemVariantService::class)->sync($item, [
            ['attributes' => ['size' => 'S'], 'sku' => 'ADJ-S', 'is_default' => true],
            ['attributes' => ['size' => 'L'], 'sku' => 'ADJ-L'],
        ]);

        $large = $variants->firstWhere('sku', 'ADJ-L');
        $this->receive($item, 10, 20, variantId: $large->id);

        $adjustment = $this->adjust($item, [
            'items' => [[
                'item_id' => $item->id,
                'variant_id' => $large->id,
                'unit_measure_id' => $item->unit_measure_id,
                'quantity' => 2,
            ]],
        ]);

        $this->assertSame(
            $large->id,
            StockAdjustmentItem::where('stock_adjustment_id', $adjustment->id)->value('variant_id'),
        );
    }

    public function test_an_out_line_is_costed_with_the_company_costing_method(): void
    {
        $this->ctx['company']->update(['costing_method' => CostingMethod::FIFO->value]);

        $item = $this->ctx['item'];
        app(ItemVariantService::class)->ensureDefault($item);

        // Two layers at different costs. FIFO must take the older, cheaper one.
        $this->receive($item, 5, 10);
        $this->receive($item, 5, 30);

        $reason = collect(StockAdjustmentReason::cases())
            ->first(fn ($case) => $case->direction() === StockMovementType::OUT);

        $adjustment = $this->adjust($item, [
            'reason' => $reason->value,
            'items' => [[
                'item_id' => $item->id,
                'unit_measure_id' => $item->unit_measure_id,
                'quantity' => 2,
            ]],
        ]);

        // This read a global `costing_method` cache key that nothing writes any
        // more, so it silently fell back to weighted average (20) while
        // StockService consumed the FIFO layer at 10.
        $this->assertEqualsWithDelta(
            10.0,
            (float) StockAdjustmentItem::where('stock_adjustment_id', $adjustment->id)->value('unit_cost'),
            0.0001,
        );
    }

    public function test_an_out_line_on_a_batch_is_costed_from_that_batch(): void
    {
        $this->ctx['company']->update(['costing_method' => CostingMethod::FIFO->value]);

        $item = Item::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'asset_account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'code' => 'ADJ-BATCH',
            'is_batch_tracked' => true,
        ]);

        app(ItemVariantService::class)->ensureDefault($item);

        // The cheap batch is older, so an unfiltered FIFO peek would grab it.
        $this->receive($item, 5, 10, 'OLD-CHEAP');
        $this->receive($item, 5, 40, 'NEW-DEAR');

        $reason = collect(StockAdjustmentReason::cases())
            ->first(fn ($case) => $case->direction() === StockMovementType::OUT);

        $adjustment = $this->adjust($item, [
            'reason' => $reason->value,
            'items' => [[
                'item_id' => $item->id,
                'unit_measure_id' => $item->unit_measure_id,
                'quantity' => 1,
                'batch' => 'NEW-DEAR',
            ]],
        ]);

        // Writing off the expensive batch has to cost the expensive batch.
        $this->assertEqualsWithDelta(
            40.0,
            (float) StockAdjustmentItem::where('stock_adjustment_id', $adjustment->id)->value('unit_cost'),
            0.0001,
        );
    }
}

<?php

namespace Tests\Unit\Services;

use App\Enums\CostingMethod;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\ItemVariant;
use App\Services\ItemVariantService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * What a variant's stock is worth.
 *
 * items.avg_cost blends every variant together, which prices an 8GB phone and
 * a 32GB one identically the moment they were bought for different money. Each
 * variant carries its own average of the receipts that actually landed on it.
 */
class VariantAverageCostTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext(CostingMethod::FIFO->value);
    }

    private function makeVariant(string $name, array $attributes): ItemVariant
    {
        // Seed the default first, so the item always has one and the rows under
        // test are the extra ones a variant grid would add.
        app(ItemVariantService::class)->ensureDefault($this->ctx['item']);

        $variant = new ItemVariant();
        $variant->fill([
            'item_id' => $this->ctx['item']->id,
            'branch_id' => $this->ctx['branch']->id,
            'attributes' => $attributes,
            'name' => $name,
            'is_default' => false,
            'is_active' => true,
        ]);
        $variant->save();

        return $variant;
    }

    private function receive(ItemVariant $variant, float $quantity, float $unitCost, string $date, ?string $unitMeasureId = null): void
    {
        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'variant_id' => $variant->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $unitMeasureId ?? $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'source' => StockSourceType::PURCHASE->value,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'date' => $date,
            'expire_date' => null,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'variant-average-cost-test',
            'reference_id' => $this->ctx['item']->id,
        ]);
    }

    public function test_a_receipt_sets_the_variant_average_cost(): void
    {
        $variant = $this->makeVariant('8gb / 500gb', ['ram' => '8gb', 'storage' => '500gb']);

        $this->receive($variant, 10, 26000, '2026-03-01');

        $this->assertEquals(26000.0, (float) $variant->fresh()->avg_cost);
    }

    public function test_a_second_receipt_blends_into_the_variant_average(): void
    {
        $variant = $this->makeVariant('8gb / 500gb', ['ram' => '8gb', 'storage' => '500gb']);

        $this->receive($variant, 10, 26000, '2026-03-01');
        $this->receive($variant, 10, 30000, '2026-03-02');

        $this->assertEqualsWithDelta(28000.0, (float) $variant->fresh()->avg_cost, 0.0001);
    }

    public function test_each_variant_keeps_its_own_average(): void
    {
        $small = $this->makeVariant('8gb / 500gb', ['ram' => '8gb', 'storage' => '500gb']);
        $large = $this->makeVariant('32gb / 1000gb', ['ram' => '32gb', 'storage' => '1000gb']);

        $this->receive($small, 10, 26000, '2026-03-01');
        $this->receive($large, 10, 45000, '2026-03-02');

        $this->assertEquals(26000.0, (float) $small->fresh()->avg_cost);
        $this->assertEquals(45000.0, (float) $large->fresh()->avg_cost);

        // The item-wide figure is still the blend of both — the two answer
        // different questions and neither replaces the other.
        $this->assertEqualsWithDelta(35500.0, (float) $this->ctx['item']->fresh()->avg_cost, 0.0001);
    }

    public function test_a_receipt_onto_stock_that_carries_no_average_does_not_blend_into_zero(): void
    {
        $variant = $this->makeVariant('8gb / 500gb', ['ram' => '8gb', 'storage' => '500gb']);

        // The opening: received before this column was maintained, so it left
        // the variant with stock on hand and avg_cost still at 0.
        $this->receive($variant, 15, 34000, '2026-09-21');
        $variant->forceFill(['avg_cost' => 0])->save();

        $this->receive($variant, 10, 35000, '2026-09-21');

        // (15 x 34,000 + 10 x 35,000) / 25. Blending into the absent average
        // would have valued the opening 15 at nothing and returned 14,000.
        $this->assertEqualsWithDelta(34400.0, (float) $variant->fresh()->avg_cost, 0.0001);
    }

    public function test_the_replay_costs_every_layer_in_the_items_own_unit(): void
    {
        $boxMeasure = UnitMeasure::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'quantity_id' => $this->ctx['quantity']->id,
            'name' => 'Box',
            'unit' => '6',
            'symbol' => 'box',
            'is_active' => true,
        ]);

        $variant = $this->makeVariant('8gb / 500gb', ['ram' => '8gb', 'storage' => '500gb']);

        // 2 boxes of 6 at 60/box is 12 pieces at 10/piece.
        $this->receive($variant, 2, 60, '2026-03-01', $boxMeasure->id);
        $this->receive($variant, 12, 14, '2026-03-02');

        app(StockService::class)->recalculateVariantAverageCosts($this->ctx['item']->id);

        // (12 x 10 + 12 x 14) / 24 = 12 per piece. Replaying the raw columns
        // would have blended 60 with 14 and produced a cost in no unit at all.
        $this->assertEqualsWithDelta(12.0, (float) $variant->fresh()->avg_cost, 0.0001);
    }

    public function test_replaying_rebuilds_the_variant_average_after_a_receipt_is_removed(): void
    {
        $variant = $this->makeVariant('8gb / 500gb', ['ram' => '8gb', 'storage' => '500gb']);

        $this->receive($variant, 10, 26000, '2026-03-01');
        $this->receive($variant, 10, 30000, '2026-03-02');

        // The dearer receipt is cancelled: the running average cannot be
        // unwound from the middle of the history, so the rebuild replays.
        \App\Models\Inventory\StockMovement::query()
            ->where('variant_id', $variant->id)
            ->where('unit_cost', 30000)
            ->delete();

        app(StockService::class)->recalculateVariantAverageCosts($this->ctx['item']->id);

        $this->assertEquals(26000.0, (float) $variant->fresh()->avg_cost);
    }
}

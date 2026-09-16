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
use App\Models\Inventory\StockBalance;
use App\Services\ItemVariantService;
use App\Services\StockAdjustmentService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Reversing a posted adjustment must put stock back exactly where it was:
 * the quantity restored, and the average cost untouched by what is really
 * just an undo, not a new receipt at the adjustment's cost.
 */
class StockAdjustmentReversalTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);

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

    private function receive(Item $item, float $quantity, float $unitCost): void
    {
        app(StockService::class)->post([
            'item_id' => $item->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $item->unit_measure_id,
            'quantity' => $quantity,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => $unitCost,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'expire_date' => null,
            'date' => now()->toDateString(),
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $item->branch_id,
        ]);
    }

    private function totalOnHand(Item $item): float
    {
        return (float) StockBalance::query()
            ->where('item_id', $item->id)
            ->where('warehouse_id', $this->ctx['warehouse']->id)
            ->sum('quantity');
    }

    public function test_reversing_an_out_adjustment_restores_the_quantity(): void
    {
        $item = $this->ctx['item'];
        app(ItemVariantService::class)->ensureDefault($item);
        $this->receive($item, 10, 20);

        $reason = collect(StockAdjustmentReason::cases())
            ->first(fn ($case) => $case->direction() === StockMovementType::OUT);

        $adjustment = app(StockAdjustmentService::class)->create([
            'date' => now()->toDateString(),
            'reason' => $reason->value,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'items' => [[
                'item_id' => $item->id,
                'unit_measure_id' => $item->unit_measure_id,
                'quantity' => 4,
            ]],
        ]);

        $this->assertEqualsWithDelta(6.0, $this->totalOnHand($item), 0.0001);

        app(StockAdjustmentService::class)->reverse($adjustment, 'entered by mistake');

        $this->assertEqualsWithDelta(
            10.0,
            $this->totalOnHand($item),
            0.0001,
            'Reversing an OUT adjustment should put the deducted quantity back on the shelf.',
        );
    }

    public function test_reversing_an_out_adjustment_does_not_change_the_average_cost(): void
    {
        $item = $this->ctx['item'];
        app(ItemVariantService::class)->ensureDefault($item);
        $this->receive($item, 10, 20);

        $this->assertEqualsWithDelta(20.0, (float) $item->fresh()->avg_cost, 0.0001);

        $reason = collect(StockAdjustmentReason::cases())
            ->first(fn ($case) => $case->direction() === StockMovementType::OUT);

        $adjustment = app(StockAdjustmentService::class)->create([
            'date' => now()->toDateString(),
            'reason' => $reason->value,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'items' => [[
                'item_id' => $item->id,
                'unit_measure_id' => $item->unit_measure_id,
                'quantity' => 4,
            ]],
        ]);

        // An OUT never moves the average cost; only confirming the baseline
        // the reversal must not disturb.
        $this->assertEqualsWithDelta(20.0, (float) $item->fresh()->avg_cost, 0.0001);

        app(StockAdjustmentService::class)->reverse($adjustment, 'entered by mistake');

        // Putting the 4 units back is not a new receipt at the adjustment's
        // cost — it must not re-blend into the average.
        $this->assertEqualsWithDelta(
            20.0,
            (float) $item->fresh()->avg_cost,
            0.0001,
            'Reversing an OUT adjustment must not re-blend its cost into the average.',
        );
    }

    public function test_reversing_an_out_adjustment_on_fifo_does_not_disturb_the_average_cost(): void
    {
        $this->ctx['company']->update(['costing_method' => CostingMethod::FIFO->value]);

        $item = $this->ctx['item'];
        app(ItemVariantService::class)->ensureDefault($item);

        // Two layers at different costs, so the OUT is costed off the older,
        // cheaper layer while the average blends both: (5*10 + 5*30) / 10 = 20.
        $this->receive($item, 5, 10);
        $this->receive($item, 5, 30);
        $this->assertEqualsWithDelta(20.0, (float) $item->fresh()->avg_cost, 0.0001);

        $reason = collect(StockAdjustmentReason::cases())
            ->first(fn ($case) => $case->direction() === StockMovementType::OUT);

        $adjustment = app(StockAdjustmentService::class)->create([
            'date' => now()->toDateString(),
            'reason' => $reason->value,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'items' => [[
                'item_id' => $item->id,
                'unit_measure_id' => $item->unit_measure_id,
                'quantity' => 2,
            ]],
        ]);

        // An OUT never moves the average, whatever layer it was costed from.
        $this->assertEqualsWithDelta(20.0, (float) $item->fresh()->avg_cost, 0.0001);

        app(StockAdjustmentService::class)->reverse($adjustment, 'entered by mistake');

        // Putting the layer-1 units back at their layer-1 cost (10) must not
        // re-blend that cost into an average that was never disturbed by the
        // OUT in the first place.
        $this->assertEqualsWithDelta(
            20.0,
            (float) $item->fresh()->avg_cost,
            0.0001,
            'Reversing a FIFO-costed OUT adjustment must not re-blend the layer cost into the average.',
        );
        $this->assertEqualsWithDelta(10.0, $this->totalOnHand($item), 0.0001);
    }

    public function test_reversing_an_in_adjustment_restores_the_quantity(): void
    {
        $item = $this->ctx['item'];
        app(ItemVariantService::class)->ensureDefault($item);
        $this->receive($item, 10, 20);

        $reason = collect(StockAdjustmentReason::cases())
            ->first(fn ($case) => $case->direction() === StockMovementType::IN);

        $adjustment = app(StockAdjustmentService::class)->create([
            'date' => now()->toDateString(),
            'reason' => $reason->value,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'items' => [[
                'item_id' => $item->id,
                'unit_measure_id' => $item->unit_measure_id,
                'quantity' => 5,
                'unit_cost' => 30,
            ]],
        ]);

        $this->assertEqualsWithDelta(15.0, $this->totalOnHand($item), 0.0001);

        app(StockAdjustmentService::class)->reverse($adjustment, 'entered by mistake');

        $this->assertEqualsWithDelta(
            10.0,
            $this->totalOnHand($item),
            0.0001,
            'Reversing an IN adjustment should remove the found quantity again.',
        );
    }

    /**
     * The mirror of the OUT case, and the one that actually costs money.
     *
     * An IN *does* move the average when it is posted, so undoing it has to
     * move the average back. Leaving the average where the cancelled receipt
     * put it mis-states the value of every unit still on the shelf, and every
     * COGS figure taken from it afterwards.
     */
    public function test_reversing_an_in_adjustment_puts_the_average_cost_back(): void
    {
        $item = $this->ctx['item'];
        app(ItemVariantService::class)->ensureDefault($item);
        $this->receive($item, 10, 20);

        $this->assertEqualsWithDelta(20.0, (float) $item->fresh()->avg_cost, 0.0001);

        $reason = collect(StockAdjustmentReason::cases())
            ->first(fn ($case) => $case->direction() === StockMovementType::IN);

        $adjustment = app(StockAdjustmentService::class)->create([
            'date' => now()->toDateString(),
            'reason' => $reason->value,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'items' => [[
                'item_id' => $item->id,
                'unit_measure_id' => $item->unit_measure_id,
                'quantity' => 5,
                'unit_cost' => 30,
            ]],
        ]);

        // The receipt blended in: (10 x 20 + 5 x 30) / 15 = 23.3333.
        $this->assertEqualsWithDelta(23.3333, (float) $item->fresh()->avg_cost, 0.001);

        app(StockAdjustmentService::class)->reverse($adjustment, 'entered by mistake');

        $this->assertEqualsWithDelta(10.0, $this->totalOnHand($item), 0.0001);
        $this->assertEqualsWithDelta(
            20.0,
            (float) $item->fresh()->avg_cost,
            0.0001,
            'Reversing an IN adjustment must unwind its effect on the average cost, '
            .'not leave the average where the cancelled receipt put it.',
        );
    }
}

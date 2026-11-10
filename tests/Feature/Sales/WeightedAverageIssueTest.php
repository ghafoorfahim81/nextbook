<?php

namespace Tests\Feature\Sales;

use App\Enums\CostingMethod;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Sale\Sale;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * A weighted-average issue is priced at the one average, but it still comes
 * off real batches — and has to be able to go back onto them.
 */
class WeightedAverageIssueTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext(CostingMethod::WEIGHTED_AVERAGE->value);
        $this->ctx['item']->update(['is_batch_tracked' => true, 'is_expiry_tracked' => true]);
    }

    private function receive(float $quantity, float $unitCost, string $batch, string $expireDate): void
    {
        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => $unitCost,
            'status' => StockStatus::POSTED->value,
            'batch' => $batch,
            'date' => '2026-03-01',
            'expire_date' => $expireDate,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'seed-stock',
            'reference_id' => $this->ctx['item']->id,
        ]);
    }

    private function onHand(string $batch): float
    {
        return (float) StockBalance::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('warehouse_id', $this->ctx['warehouse']->id)
            ->where('batch', $batch)
            ->sum('quantity');
    }

    public function test_an_issue_records_the_batches_it_came_from_and_a_reversal_puts_them_back(): void
    {
        $this->receive(10, 10, 'B-1', '2027-01-01');
        $this->receive(10, 20, 'B-2', '2027-06-01');

        // No batch named on the line: nearest expiry goes first, 10 from B-1 then 2 from B-2.
        $this->post(route('sales.store'), [
            'number' => 7200,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-19',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => 12,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 40,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('number', 7200)->firstOrFail();

        $this->assertEquals(0.0, $this->onHand('B-1'));
        $this->assertEquals(8.0, $this->onHand('B-2'));

        $issued = StockMovement::query()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->where('movement_type', StockMovementType::OUT->value)
            ->orderBy('expire_date')
            ->get();

        // One row per batch, both at the one average (10 x 10 + 10 x 20) / 20.
        $this->assertSame(['B-1', 'B-2'], $issued->pluck('batch')->all());
        $this->assertEquals([10.0, 2.0], $issued->map(fn ($m) => (float) $m->quantity)->all());
        $this->assertEquals([15.0, 15.0], $issued->map(fn ($m) => (float) $m->unit_cost)->all());

        $this->post(route('sales.reverse', $sale), ['reason' => 'wrong customer'])->assertRedirect();

        // Back on the batches it left, not in a bucket with no batch at all.
        $this->assertEquals(10.0, $this->onHand('B-1'));
        $this->assertEquals(10.0, $this->onHand('B-2'));
        $this->assertEquals(20.0, (float) StockBalance::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('warehouse_id', $this->ctx['warehouse']->id)
            ->sum('quantity'));
    }
}

<?php

namespace Tests\Integration;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Services\ReportService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class StockMovementBalanceIntegrationTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_stock_movements_update_batch_and_expiry_balances_and_valuation_reports(): void
    {
        $stockService = app(StockService::class);
        $reportService = app(ReportService::class);

        $this->ctx['item']->update([
            'minimum_stock' => 10,
            'is_batch_tracked' => true,
            'is_expiry_tracked' => true,
        ]);

        $base = [
            'item_id' => $this->ctx['item']->id,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'source' => StockSourceType::PURCHASE->value,
            'status' => StockStatus::POSTED->value,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'integration-stock',
            'reference_id' => $this->ctx['item']->id,
        ];

        $stockService->post(array_merge($base, [
            'movement_type' => StockMovementType::IN->value,
            'quantity' => 6,
            'unit_cost' => 20,
            'batch' => 'LOT-A',
            'expire_date' => '2027-01-01',
            'date' => '2026-03-01',
        ]));

        $stockService->post(array_merge($base, [
            'movement_type' => StockMovementType::IN->value,
            'quantity' => 5,
            'unit_cost' => 30,
            'batch' => 'LOT-B',
            'expire_date' => '2027-02-01',
            'date' => '2026-03-02',
        ]));

        $stockService->post(array_merge($base, [
            'movement_type' => StockMovementType::OUT->value,
            'quantity' => 2,
            'unit_cost' => 0,
            'batch' => 'LOT-A',
            'expire_date' => '2027-01-01',
            'date' => '2026-03-03',
        ]));

        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'batch' => 'LOT-A',
            'quantity' => 4.0000,
        ]);

        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'batch' => 'LOT-B',
            'quantity' => 5.0000,
        ]);

        $commonFilters = [
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'ledger_id' => null,
            'customer_id' => null,
            'supplier_id' => null,
            'item_id' => null,
            'account_id' => null,
            'per_page' => 25,
            'page' => 1,
        ];

        $valuation = $reportService->getInventoryValuation(array_merge($commonFilters, ['report' => 'inventory_valuation']));
        $lowStock = $reportService->getLowStock(array_merge($commonFilters, ['report' => 'low_stock']));

        $this->assertEquals(9.0, $valuation['summary']['total_quantity']);
        $this->assertEquals(230.0, $valuation['summary']['total_value']);
        $this->assertEquals(1, $lowStock['summary']['total_items']);
    }
}

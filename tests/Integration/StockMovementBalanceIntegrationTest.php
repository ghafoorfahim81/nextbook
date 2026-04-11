<?php

namespace Tests\Integration;

use App\Enums\ItemType;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Inventory\StockBalance;
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

    public function test_item_opening_update_reuses_existing_balance_row_when_batch_or_expiry_changes(): void
    {
        $stockService = app(StockService::class);

        $this->ctx['item']->update([
            'is_batch_tracked' => true,
            'is_expiry_tracked' => true,
        ]);

        $openingMovement = $stockService->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 10,
            'source' => StockSourceType::OPENING->value,
            'unit_cost' => 20,
            'status' => StockStatus::DRAFT->value,
            'batch' => 'LOT-OLD',
            'date' => '2026-03-01',
            'expire_date' => '2027-01-01',
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'opening-update-test',
            'reference_id' => $this->ctx['item']->id,
        ])[0];

        $originalBalance = StockBalance::query()->where([
            'item_id' => $this->ctx['item']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'batch' => 'LOT-OLD',
        ])->firstOrFail();

        $response = $this->patch(route('items.update', $this->ctx['item']), [
            'name' => $this->ctx['item']->name,
            'code' => $this->ctx['item']->code,
            'item_type' => ItemType::INVENTORY_MATERIALS->value,
            'sku' => $this->ctx['item']->sku,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'brand_id' => null,
            'category_id' => null,
            'asset_account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'income_account_id' => $this->ctx['accounts']['product-income']->id,
            'cost_account_id' => $this->ctx['accounts']['cost-of-goods-sold']->id,
            'minimum_stock' => 5,
            'maximum_stock' => 100,
            'colors' => [],
            'size_id' => $this->ctx['size']->id,
            'purchase_price' => null,
            'cost' => null,
            'sale_price' => null,
            'margin_percentage' => null,
            'rate_a' => null,
            'rate_b' => null,
            'rate_c' => null,
            'rack_no' => null,
            'fast_search' => null,
            'is_batch_tracked' => true,
            'is_expiry_tracked' => true,
            'openings' => [
                [
                    'id' => $openingMovement->id,
                    'batch' => 'LOT-NEW',
                    'expire_date' => '2027-02-01',
                    'quantity' => 10,
                    'unit_price' => 20,
                    'warehouse_id' => $this->ctx['warehouse']->id,
                    'status' => StockStatus::DRAFT->value,
                ],
            ],
        ]);

        $response->assertRedirect(route('items.index'));

        $balances = StockBalance::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('warehouse_id', $this->ctx['warehouse']->id)
            ->get();

        $this->assertCount(1, $balances);

        $balance = $balances->first();
        $this->assertSame($originalBalance->id, $balance->id);
        $this->assertSame('LOT-NEW', $balance->batch);
        $this->assertSame('2027-02-01', $balance->expire_date?->toDateString());
        $this->assertSame('10.0000', (string) $balance->quantity);
    }
}

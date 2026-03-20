<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransferStatus;
use App\Models\Administration\Warehouse;
use App\Models\ItemTransfer\ItemTransfer;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class ItemTransferFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_it_transfers_stock_between_warehouses_and_updates_balances(): void
    {
        $toWarehouse = Warehouse::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Secondary Warehouse',
        ]);

        $this->ctx['item']->update(['is_batch_tracked' => true, 'is_expiry_tracked' => true]);

        $stockService = app(StockService::class);

        $stockService->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 10,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => 15,
            'status' => StockStatus::POSTED->value,
            'batch' => 'LOT-1',
            'date' => '2026-03-10',
            'expire_date' => '2027-03-10',
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'seed-transfer-stock',
            'reference_id' => $this->ctx['item']->id,
        ]);

        $storeResponse = $this->post(route('item-transfers.store'), [
            'date' => '2026-03-19',
            'from_warehouse_id' => $this->ctx['warehouse']->id,
            'to_warehouse_id' => $toWarehouse->id,
            'transfer_cost' => 0,
            'remarks' => 'stock transfer',
            'items' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'batch' => 'LOT-1',
                    'expire_date' => '2027-03-10',
                    'quantity' => 4,
                    'measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => 15,
                ],
            ],
        ]);

        $storeResponse->assertRedirect(route('item-transfers.index'));

        $transfer = ItemTransfer::query()->latest()->firstOrFail();

        $completeResponse = $this->patch(route('item-transfers.complete', $transfer));
        $completeResponse->assertRedirect();

        $transfer->refresh();
        $this->assertEquals(TransferStatus::COMPLETED->value, $transfer->status->value);

        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'batch' => 'LOT-1',
            'quantity' => 6.0000,
        ]);

        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'warehouse_id' => $toWarehouse->id,
            'batch' => 'LOT-1',
            'quantity' => 4.0000,
        ]);
    }
}

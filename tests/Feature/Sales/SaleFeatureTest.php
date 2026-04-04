<?php

namespace Tests\Feature\Sales;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Inventory\StockMovement;
use App\Models\Sale\Sale;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class SaleFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_sale_creation_deducts_stock_posts_revenue_and_updates_customer_balance(): void
    {
        $stockService = app(StockService::class);

        $stockService->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 15,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => 12,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'date' => '2026-03-10',
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'seed-sale-stock',
            'reference_id' => $this->ctx['item']->id,
        ]);

        $response = $this->post(route('sales.store'), [
            'number' => 7001,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => 200,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'description' => 'sale feature test',
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'batch' => null,
                    'expire_date' => null,
                    'quantity' => 5,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => 40,
                    'item_discount' => 0,
                    'free' => 0,
                    'tax' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.index'));

        $sale = Sale::query()->latest()->firstOrFail();

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'item_id' => $this->ctx['item']->id,
            'quantity' => 5.00,
            'unit_price' => 40.0000,
        ]);

        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'quantity' => 10.0000,
        ]);

        $this->assertDatabaseHas('transactions', [
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'status' => 'posted',
        ]);

        $customerBalance = DB::table('transaction_lines')
            ->where('ledger_id', $this->ctx['customer_ledger']->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->value('balance');

        $this->assertEquals(200.0, (float) $customerBalance);
    }

    public function test_sale_creation_auto_allocates_fifo_batches_when_batch_is_not_selected(): void
    {
        $stockService = app(StockService::class);
        $this->ctx['item']->update([
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
            'reference_type' => 'seed-sale-batch-stock',
            'reference_id' => $this->ctx['item']->id,
        ];

        $stockService->post(array_merge($base, [
            'movement_type' => StockMovementType::IN->value,
            'quantity' => 10,
            'unit_cost' => 12,
            'batch' => 'Batch 001',
            'expire_date' => '2027-01-01',
            'date' => '2026-03-10',
        ]));

        $stockService->post(array_merge($base, [
            'movement_type' => StockMovementType::IN->value,
            'quantity' => 7,
            'unit_cost' => 14,
            'batch' => 'Batch 002',
            'expire_date' => '2027-02-01',
            'date' => '2026-03-11',
        ]));

        $response = $this->post(route('sales.store'), [
            'number' => 7002,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => 600,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'description' => 'sale fifo batch fallback',
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'batch' => null,
                    'expire_date' => null,
                    'quantity' => 15,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => 40,
                    'item_discount' => 0,
                    'free' => 0,
                    'tax' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.index'));

        $outMovements = StockMovement::query()
            ->where('reference_type', Sale::class)
            ->where('movement_type', StockMovementType::OUT->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $outMovements);
        $this->assertSame('Batch 001', $outMovements[0]->batch);
        $this->assertEquals(10.0, (float) $outMovements[0]->quantity);
        $this->assertSame('Batch 002', $outMovements[1]->batch);
        $this->assertEquals(5.0, (float) $outMovements[1]->quantity);

        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'batch' => 'Batch 001',
            'quantity' => 0.0000,
        ]);

        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'batch' => 'Batch 002',
            'quantity' => 2.0000,
        ]);
    }
}

<?php

namespace Tests\Integration;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Sale\Sale;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class SaleInventoryIntegrationTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_sale_flow_deducts_inventory_and_posts_customer_receivable(): void
    {
        $stockService = app(StockService::class);

        $stockService->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 6,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => 10,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'date' => '2026-03-10',
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'integration-sale-seed',
            'reference_id' => $this->ctx['item']->id,
        ]);

        $response = $this->post(route('sales.store'), [
            'number' => 9101,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => 120,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'batch' => null,
                    'expire_date' => null,
                    'quantity' => 3,
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

        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'quantity' => 3.0000,
        ]);

        $customerBalance = DB::table('transaction_lines')
            ->where('ledger_id', $this->ctx['customer_ledger']->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->value('balance');

        $this->assertEquals(120.0, (float) $customerBalance);

        $this->delete(route('sales.destroy', $sale))->assertRedirect(route('sales.index'));
        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
    }

    public function test_sale_cannot_deduct_more_than_available_stock(): void
    {
        $stockService = app(StockService::class);

        $stockService->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 2,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => 10,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'date' => '2026-03-10',
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'integration-sale-seed-2',
            'reference_id' => $this->ctx['item']->id,
        ]);

        $response = $this->from(route('sales.create'))
            ->post(route('sales.store'), [
                'number' => 9102,
                'customer_id' => $this->ctx['customer_ledger']->id,
                'date' => '2026-03-19',
                'transaction_total' => 200,
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'sale_type' => 'cash',
                'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                'warehouse_id' => $this->ctx['warehouse']->id,
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

        $response->assertRedirect(route('sales.create'));
        $response->assertSessionHasErrors();
    }
}

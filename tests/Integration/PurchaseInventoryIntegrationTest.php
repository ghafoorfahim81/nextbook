<?php

namespace Tests\Integration;

use App\Models\Purchase\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class PurchaseInventoryIntegrationTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_credit_purchase_updates_inventory_and_payable_for_partial_payment(): void
    {
        $response = $this->post(route('purchases.store'), [
            'number' => 9001,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => 100,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'purchase_type' => 'credit',
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'payment' => [
                'amount' => 20,
                'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            ],
            'warehouse_id' => $this->ctx['warehouse']->id,
            'description' => 'integration purchase',
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'batch' => null,
                    'expire_date' => null,
                    'quantity' => 5,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => 20,
                    'item_discount' => 0,
                    'free' => 0,
                    'tax' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchases.index'));

        $purchase = Purchase::query()->latest()->firstOrFail();

        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'quantity' => 5.0000,
        ]);

        $this->assertDatabaseHas('transactions', [
            'reference_type' => Purchase::class,
            'reference_id' => $purchase->id,
        ]);

        $supplierBalance = DB::table('transaction_lines')
            ->where('ledger_id', $this->ctx['supplier_ledger']->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->value('balance');

        $this->assertEquals(-80.0, (float) $supplierBalance);
    }

    public function test_purchase_delete_soft_deletes_related_records(): void
    {
        $this->post(route('purchases.store'), [
            'number' => 9002,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-20',
            'transaction_total' => 60,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'batch' => null,
                    'expire_date' => null,
                    'quantity' => 3,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => 20,
                    'item_discount' => 0,
                    'free' => 0,
                    'tax' => 0,
                ],
            ],
        ])->assertRedirect();

        $purchase = Purchase::query()->latest()->firstOrFail();
        $this->delete(route('purchases.destroy', $purchase))->assertRedirect(route('purchases.index'));

        $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);
        $this->assertSoftDeleted('purchase_items', ['purchase_id' => $purchase->id]);
    }
}

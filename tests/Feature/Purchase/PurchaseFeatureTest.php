<?php

namespace Tests\Feature\Purchase;

use App\Models\Purchase\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class PurchaseFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_purchase_creation_inserts_items_posts_transaction_and_updates_stock(): void
    {
        $response = $this->post(route('purchases.store'), [
            'number' => 5001,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => 300,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'discount' => 10,
            'discount_type' => 'percentage',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'description' => 'purchase feature test',
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'batch' => 'BT-100',
                    'expire_date' => '2027-03-01',
                    'quantity' => 10,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => 30,
                    'item_discount' => 5,
                    'free' => 0,
                    'tax' => 2,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchases.index'));

        $purchase = Purchase::query()->latest()->firstOrFail();

        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchase->id,
            'item_id' => $this->ctx['item']->id,
            'quantity' => 10.00,
            'discount' => 5.0000,
            'tax' => 2.0000,
        ]);

        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'quantity' => 10.0000,
        ]);

        $this->assertDatabaseHas('transactions', [
            'reference_type' => Purchase::class,
            'reference_id' => $purchase->id,
            'status' => 'posted',
        ]);

        $totals = DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('t.reference_type', Purchase::class)
            ->where('t.reference_id', $purchase->id)
            ->selectRaw('SUM(tl.debit) as debit_total, SUM(tl.credit) as credit_total')
            ->first();

        $this->assertEquals((float) $totals->debit_total, (float) $totals->credit_total);
    }
}

<?php

namespace Tests\Integration;

use App\Enums\TransactionStatus;
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

    /**
     * A draft is still just a piece of paper, so deleting it takes its lines
     * with it. This used to be the only case here, from when every purchase
     * was created as a draft; purchases now post on create by default (see the
     * transaction.purchase_post_immediately preference), so the draft has to be
     * asked for explicitly and the posted case is covered separately below.
     */
    public function test_a_draft_purchase_delete_soft_deletes_related_records(): void
    {
        $this->asDraftOnCreate();

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

    /**
     * A posted purchase has moved stock and hit the ledger, so it is reversed,
     * never deleted. The index disables the delete action for posted rows; this
     * is the server refusing it even so.
     */
    public function test_a_posted_purchase_cannot_be_deleted(): void
    {
        $this->post(route('purchases.store'), [
            'number' => 9003,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-20',
            'transaction_total' => 60,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => 3,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 20,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect();

        $purchase = Purchase::query()->latest()->firstOrFail();
        $this->assertSame(TransactionStatus::POSTED->value, $purchase->status);

        $this->delete(route('purchases.destroy', $purchase))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('purchases', ['id' => $purchase->id]);
    }

    /** Create documents as drafts, the way the preference toggle does. */
    private function asDraftOnCreate(): void
    {
        $user = $this->ctx['user'];
        $preferences = $user->preferences ?? [];
        $preferences['transaction']['purchase_post_immediately'] = false;
        $user->forceFill(['preferences' => $preferences])->save();
        $this->actingAs($user->fresh());
    }
}

<?php

namespace Tests\Feature\Purchase;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Enums\TransactionStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Transaction\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The rules a purchase has to hold to whatever the form sends: what it comes
 * to, how it is paid, what its stock costs, and what a return gives back.
 */
class PurchaseHardeningTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    private function line(array $overrides = []): array
    {
        return array_merge([
            'item_id' => $this->ctx['item']->id,
            'batch' => null,
            'expire_date' => null,
            'quantity' => 10,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'unit_price' => 30,
            'item_discount' => 0,
            'free' => 0,
            'tax' => 0,
        ], $overrides);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'number' => 5001,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => 300,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'purchase_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [$this->line()],
        ], $overrides);
    }

    private function cash(): string
    {
        return $this->ctx['accounts']['cash-in-hand']->id;
    }

    private function onHand(): float
    {
        return (float) StockBalance::where('item_id', $this->ctx['item']->id)->sum('quantity');
    }

    /** What the business owes the supplier (credit − debit on their ledger). */
    private function owed(): float
    {
        return round((float) DB::table('transaction_lines')
            ->where('ledger_id', $this->ctx['supplier_ledger']->id)
            ->selectRaw('COALESCE(SUM(credit - debit), 0) as b')
            ->value('b'), 4);
    }

    private function lines(Purchase $purchase)
    {
        return DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->where('transactions.reference_type', Purchase::class)
            ->where('transactions.reference_id', $purchase->id);
    }

    public function test_payment_rules_are_validation_errors_not_crashes(): void
    {
        $this->post(route('purchases.store'), $this->payload(['purchase_type' => 'cash']))
            ->assertSessionHasErrors('bank_account_id');

        $this->post(route('purchases.store'), $this->payload([
            'purchase_type' => 'credit',
            'payment' => ['method' => 'cash', 'amount' => 400, 'account_id' => $this->cash()],
        ]))->assertSessionHasErrors('payment.amount');

        $this->post(route('purchases.store'), $this->payload(['purchase_type' => 'banana']))
            ->assertSessionHasErrors('purchase_type');

        $this->post(route('purchases.store'), $this->payload(['item_list' => [$this->line(['quantity' => -5])]]))
            ->assertSessionHasErrors('item_list.0.quantity');

        $this->assertSame(0, Purchase::count());
    }

    public function test_a_credit_purchase_without_payment_details_is_owed_in_full(): void
    {
        $this->post(route('purchases.store'), $this->payload(['purchase_type' => 'credit']))
            ->assertRedirect(route('purchases.index'));

        $purchase = Purchase::firstOrFail();
        $this->assertSame(PaymentStatus::Unpaid, $purchase->payment_status);
        $this->assertEquals(300.0, $this->owed());
    }

    public function test_a_credit_purchase_paid_in_full_is_paid(): void
    {
        $this->post(route('purchases.store'), $this->payload([
            'purchase_type' => 'credit',
            'payment' => ['method' => 'cash', 'amount' => 300, 'account_id' => $this->cash()],
        ]))->assertRedirect(route('purchases.index'));

        $purchase = Purchase::firstOrFail();
        $this->assertSame(PaymentStatus::Paid, $purchase->payment_status);
        $this->assertEquals(0.0, $this->owed());
    }

    public function test_the_total_comes_from_the_lines_and_free_units_and_discounts_set_the_cost(): void
    {
        // 10 bought + 5 free at 30, 30 off the line, 10% off the bill, 15 tax:
        // 300 − 30 − 30 + 15 = 255 for 15 units, 17 each. The form's total is ignored.
        $this->post(route('purchases.store'), $this->payload([
            'transaction_total' => 99999,
            'discount_total' => 1,
            'discount' => 10,
            'discount_type' => 'percentage',
            'item_list' => [$this->line(['free' => 5, 'item_discount' => 30, 'tax' => 15])],
        ]))->assertRedirect(route('purchases.index'));

        $purchase = Purchase::with('items')->firstOrFail();

        $this->assertEquals(15.0, $this->onHand());
        $this->assertEquals(17.0, (float) StockMovement::where('reference_id', $purchase->id)->value('unit_cost'));
        $this->assertEquals(17.0, (float) $this->ctx['item']->fresh()->avg_cost);
        $this->assertEquals(255.0, $this->owed());
        $this->assertEquals(255.0, $purchase->purchaseTotal());
        $this->assertEquals(
            255.0,
            (float) $this->lines($purchase)->where('transaction_lines.account_id', $this->ctx['item']->asset_account_id)->sum('debit'),
        );
        $this->assertEquals((float) $this->lines($purchase)->sum('debit'), (float) $this->lines($purchase)->sum('credit'));
    }

    public function test_a_fixed_bill_discount_comes_off_once(): void
    {
        $this->post(route('purchases.store'), $this->payload([
            'discount' => 30,
            'discount_type' => 'currency',
            'item_list' => [$this->line(), $this->line(['quantity' => 5, 'unit_price' => 20])],
        ]))->assertRedirect(route('purchases.index'));

        $purchase = Purchase::with('items')->firstOrFail();

        // 300 + 100 − 30, not 400 − 60.
        $this->assertEquals(370.0, $purchase->purchaseTotal());
        $this->assertEquals(370.0, $this->owed());
    }

    public function test_a_return_debits_the_supplier_what_was_paid_and_free_units_can_go_back(): void
    {
        $this->post(route('purchases.store'), $this->payload([
            'discount' => 10,
            'discount_type' => 'percentage',
            'item_list' => [$this->line(['free' => 5, 'item_discount' => 30])],
        ]))->assertRedirect(route('purchases.index'));

        $purchase = Purchase::with('items')->firstOrFail();
        $line = $purchase->items->first();

        // 240 for 15 units: 16 each.
        $this->getJson(route('purchase-returns.returnable-items', ['purchase_id' => $purchase->id]))
            ->assertOk()
            ->assertJsonPath('items.0.remaining_quantity', 15)
            ->assertJsonPath('items.0.unit_price', 16);

        $this->post(route('purchase-returns.store'), [
            'number' => 801,
            'purchase_id' => $purchase->id,
            'date' => '2026-03-20',
            'item_list' => [['purchase_item_id' => $line->id, 'quantity' => 15]],
        ])->assertRedirect();

        // Everything went back: nothing owed either way.
        $this->assertEquals(0.0, $this->owed());
        $this->assertEquals(0.0, $this->onHand());

        $this->post(route('purchase-returns.store'), [
            'number' => 802,
            'purchase_id' => $purchase->id,
            'date' => '2026-03-20',
            'item_list' => [['purchase_item_id' => $line->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('item_list');
    }

    public function test_a_draft_is_unpaid_until_paid_and_survives_delete_and_restore(): void
    {
        set_company_setting('transaction.purchase_post_immediately', false);

        $this->post(route('purchases.store'), $this->payload())->assertRedirect(route('purchases.index'));
        $purchase = Purchase::firstOrFail();

        $this->assertSame(TransactionStatus::DRAFT->value, $purchase->status);
        $this->assertSame(PaymentStatus::Unpaid, $purchase->payment_status);

        $reservedIn = fn () => (float) StockBalance::where('item_id', $this->ctx['item']->id)->sum('reserved_in');
        $this->assertEquals(10.0, $reservedIn());

        $this->delete(route('purchases.destroy', $purchase))->assertRedirect(route('purchases.index'));
        $this->assertEquals(0.0, $reservedIn());

        $this->patch(route('purchases.restore', $purchase->id))->assertRedirect(route('purchases.index'));
        $this->assertEquals(10.0, $reservedIn());
        $this->assertNotNull(Transaction::where('reference_id', $purchase->id)->first());

        $this->post(route('purchases.post', $purchase->id))->assertRedirect();

        $purchase->refresh();
        $this->assertSame(TransactionStatus::POSTED->value, $purchase->status);
        $this->assertSame(PaymentStatus::Unpaid, $purchase->payment_status);
        $this->assertEquals(10.0, $this->onHand());
        $this->assertEquals(0.0, $reservedIn());
        $this->assertEquals(300.0, $this->owed());
    }

    public function test_the_status_of_a_purchase_cannot_be_set_directly(): void
    {
        $this->assertFalse(Route::has('purchases.update-purchase-status'));
    }

    public function test_an_order_is_billed_once_and_reopened_by_reversing_its_bill(): void
    {
        $this->post(route('purchase-orders.store'), [
            'number' => 4001,
            'date' => '2026-03-19',
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'discount' => 10,
            'discount_type' => 'percentage',
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'quantity' => 10,
                'free' => 0,
                'unit_price' => 30,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'discount' => 30,
            ]],
        ])->assertRedirect();

        $order = PurchaseOrder::with('items')->firstOrFail();
        // 300 − 30 − 10% of 300: the formula the bill made from it posts.
        $this->assertEquals(240.0, $order->orderTotal());

        $this->post(route('purchases.store'), $this->payload(['purchase_order_id' => $order->id]))
            ->assertRedirect(route('purchases.index'));
        $this->assertSame(PurchaseOrderStatus::COMPLETED->value, $order->fresh()->status);

        $purchase = Purchase::firstOrFail();
        $this->post(route('purchases.reverse', $purchase), ['reason' => 'entered twice'])->assertRedirect();
        $this->assertSame(PurchaseOrderStatus::POSTED->value, $order->fresh()->status);

        set_company_setting('transaction.purchase_post_immediately', false);
        $this->post(route('purchases.store'), $this->payload(['number' => 5002, 'purchase_order_id' => $order->id]))
            ->assertRedirect(route('purchases.index'));
        $this->post(route('purchases.store'), $this->payload(['number' => 5003, 'purchase_order_id' => $order->id]))
            ->assertSessionHasErrors('purchase_order_id');
    }

    public function test_a_dollar_price_keeps_its_four_decimals(): void
    {
        $this->post(route('purchases.store'), $this->payload([
            'item_list' => [$this->line(['quantity' => 1, 'unit_price' => 0.1538])],
        ]))->assertRedirect(route('purchases.index'));

        $this->assertEquals(0.1538, Purchase::with('items')->firstOrFail()->items->first()->unit_price);
    }
}

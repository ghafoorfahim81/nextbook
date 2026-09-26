<?php

namespace Tests\Feature\Sale;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Purchase\Purchase;
use App\Models\Payment\Payment;
use App\Models\Receipt\Receipt;
use App\Models\Sale\Sale;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * A document that has been paid against cannot be reversed.
 *
 * A receipt allocated to an invoice is a claim on that invoice: the money was
 * taken against that debt specifically. Undoing the invoice underneath leaves
 * the receipt allocated to something that no longer exists, and the customer's
 * balance stops adding up. The receipt has to be reversed first.
 */
class ReverseBlockedBySettlementTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);

        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 200,
            'source' => StockSourceType::OPENING->value,
            'unit_cost' => 10,
            'status' => StockStatus::POSTED->value,
            'batch' => null, 'expire_date' => null,
            'date' => now()->toDateString(),
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => null, 'reference_id' => null,
        ]);
    }

    // ------------------------------------------------------------- helpers

    private function sellOnLoan(float $quantity = 20, float $price = 10): Sale
    {
        $this->post(route('sales.store'), [
            'number' => random_int(2000, 2999),
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => now()->toDateString(),
            'transaction_total' => $quantity * $price,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $quantity,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $price,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'));

        return Sale::query()->orderByDesc('id')->firstOrFail();
    }

    private function sellForCash(float $quantity = 20, float $price = 10): Sale
    {
        $this->post(route('sales.store'), [
            'number' => random_int(2000, 2999),
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => now()->toDateString(),
            'transaction_total' => $quantity * $price,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'cash',
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $quantity,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $price,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'));

        return Sale::query()->orderByDesc('id')->firstOrFail();
    }

    /** The receivable line the receipt will be allocated to. */
    private function claimLineOf(Sale $sale): string
    {
        return (string) DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('t.reference_type', Sale::class)
            ->where('t.reference_id', $sale->id)
            ->where('tl.ledger_id', $this->ctx['customer_ledger']->id)
            ->where('tl.debit', '>', 0)
            ->value('tl.id');
    }

    private function receiveAgainst(Sale $sale, float $amount): Receipt
    {
        $this->post(route('receipts.store'), [
            'number' => random_int(2500, 2999),
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'date' => now()->toDateString(),
            'amount' => $amount,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'narration' => 'part payment',
            'allocations' => [[
                'target_line_id' => $this->claimLineOf($sale),
                'amount' => $amount,
            ]],
        ])->assertRedirect();

        return Receipt::query()->orderByDesc('id')->firstOrFail();
    }

    private function settlementsAgainst(Sale $sale): int
    {
        return DB::table('settlements')
            ->whereIn('target_line_id', DB::table('transaction_lines')
                ->whereIn('transaction_id', DB::table('transactions')
                    ->where('reference_type', Sale::class)
                    ->where('reference_id', $sale->id)
                    ->pluck('id'))
                ->pluck('id'))
            ->whereNull('deleted_at')
            ->count();
    }

    // --------------------------------------------------------------- tests

    /**
     * A cash sale is paid on the spot inside its own voucher. If that counted
     * as "settled", no cash sale could ever be reversed — so the rule has to
     * be about money taken on a SEPARATE document.
     */
    public function test_a_cash_sale_is_not_treated_as_settled_and_still_reverses(): void
    {
        $sale = $this->sellForCash();

        $this->assertSame(
            0,
            $this->settlementsAgainst($sale),
            'A cash sale settles itself within its own voucher; nothing should point at it.',
        );

        $this->post(route('sales.reverse', $sale), ['reason' => 'keyed twice'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(TransactionStatus::REVERSED->value, $sale->fresh()->status);
    }

    public function test_a_sale_with_a_receipt_against_it_cannot_be_reversed(): void
    {
        $sale = $this->sellOnLoan();
        $receipt = $this->receiveAgainst($sale, 120);

        $this->assertGreaterThan(0, $this->settlementsAgainst($sale));

        $this->post(route('sales.reverse', $sale), ['reason' => 'keyed twice'])
            ->assertSessionHasErrors('status');

        $this->assertSame(TransactionStatus::POSTED->value, $sale->fresh()->status);
        $this->assertSame(TransactionStatus::POSTED->value, $receipt->fresh()->status);
    }

    public function test_the_refusal_names_the_receipt(): void
    {
        $sale = $this->sellOnLoan();
        $receipt = $this->receiveAgainst($sale, 120);

        $this->post(route('sales.reverse', $sale), ['reason' => 'keyed twice']);

        $message = implode(' ', session('errors')->all());

        $this->assertStringContainsString(
            (string) $receipt->number,
            $message,
            'The operator has to be told which receipt is in the way.',
        );
    }

    public function test_reversing_the_receipt_first_releases_the_sale(): void
    {
        $sale = $this->sellOnLoan();
        $receipt = $this->receiveAgainst($sale, 120);

        $this->post(route('receipts.reverse', $receipt), ['reason' => 'taken in error'])
            ->assertRedirect();

        $this->assertSame(
            0,
            $this->settlementsAgainst($sale),
            'Reversing the receipt must release what it was holding against the sale.',
        );

        $this->post(route('sales.reverse', $sale), ['reason' => 'keyed twice'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(TransactionStatus::REVERSED->value, $sale->fresh()->status);
    }

    // ------------------------------------------------------------ purchases

    private function buyOnLoan(float $quantity = 20, float $price = 10): Purchase
    {
        $this->post(route('purchases.store'), [
            'number' => random_int(2000, 2999),
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => now()->toDateString(),
            'transaction_total' => $quantity * $price,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'purchase_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $quantity,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $price,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect();

        return Purchase::query()->orderByDesc('id')->firstOrFail();
    }

    private function payAgainst(Purchase $purchase, float $amount): Payment
    {
        $lineId = (string) DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('t.reference_type', Purchase::class)
            ->where('t.reference_id', $purchase->id)
            ->where('tl.ledger_id', $this->ctx['supplier_ledger']->id)
            ->where('tl.credit', '>', 0)
            ->value('tl.id');

        $this->post(route('payments.store'), [
            // The payment number rule is `integer|max:255`, so this has to stay
            // under it — see the note in the report about that cap.
            'number' => random_int(100, 250),
            'ledger_id' => $this->ctx['supplier_ledger']->id,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'date' => now()->toDateString(),
            'amount' => $amount,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'narration' => 'part payment',
            'allocations' => [[
                'target_line_id' => $lineId,
                'amount' => $amount,
            ]],
        ])->assertRedirect();

        return Payment::query()->orderByDesc('id')->firstOrFail();
    }

    public function test_a_purchase_with_a_payment_against_it_cannot_be_reversed(): void
    {
        $purchase = $this->buyOnLoan();
        $payment = $this->payAgainst($purchase, 120);

        $this->post(route('purchases.reverse', $purchase), ['reason' => 'keyed twice'])
            ->assertSessionHasErrors('status');

        $this->assertSame(TransactionStatus::POSTED->value, $purchase->fresh()->status);
        $this->assertSame(TransactionStatus::POSTED->value, $payment->fresh()->status);
    }

    public function test_reversing_the_payment_first_releases_the_purchase(): void
    {
        $purchase = $this->buyOnLoan();
        $payment = $this->payAgainst($purchase, 120);

        $this->post(route('payments.reverse', $payment), ['reason' => 'paid in error'])
            ->assertRedirect();

        $this->post(route('purchases.reverse', $purchase), ['reason' => 'keyed twice'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(TransactionStatus::REVERSED->value, $purchase->fresh()->status);
    }
}

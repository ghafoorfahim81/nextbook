<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\SettlementException;
use App\Models\Accounting\Settlement;
use App\Models\Administration\Currency;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Services\Accounting\SettlementService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Settlement and realised foreign exchange.
 *
 * The rule under test throughout: a receivable is relieved at the rate it was
 * ORIGINALLY BOOKED at, cash moves at TODAY's rate, the difference is FX, and
 * the unpaid remainder keeps its original rate.
 */
class SettlementServiceTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private SettlementService $settlements;

    private TransactionService $transactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->settlements = app(SettlementService::class);
        $this->transactions = app(TransactionService::class);
    }

    // ------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------

    private function usd(float $rate = 60): Currency
    {
        return Currency::query()->firstOrCreate(
            ['branch_id' => $this->ctx['branch']->id, 'code' => 'USD'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'exchange_rate' => $rate,
                'is_active' => true,
                'is_base_currency' => false,
                'flag' => 'us.png',
            ]
        );
    }

    private function afn(): Currency
    {
        return $this->ctx['currency'];
    }

    private function customer(): Ledger
    {
        return $this->ctx['customer_ledger'];
    }

    private function supplier(): Ledger
    {
        return $this->ctx['supplier_ledger'];
    }

    private function account(string $slug): string
    {
        return $this->ctx['accounts'][$slug]->id;
    }

    /**
     * An invoice: debit AR, credit revenue, at a given currency and rate.
     */
    private function invoice(float $amount, Currency $currency, float $rate, string $date = '2026-01-10'): TransactionLine
    {
        $transaction = $this->transactions->post(
            header: [
                'currency_id' => $currency->id,
                'rate' => $rate,
                'date' => $date,
                'remark' => 'invoice',
            ],
            lines: [
                [
                    'account_id' => $this->account('account-receivable'),
                    'ledger_id' => $this->customer()->id,
                    'debit' => $amount,
                ],
                [
                    'account_id' => $this->account('sales-revenue'),
                    'credit' => $amount,
                ],
            ]
        );

        return $transaction->lines->firstWhere('account_id', $this->account('account-receivable'));
    }

    /**
     * A supplier bill: credit AP, debit stock.
     */
    private function bill(float $amount, Currency $currency, float $rate, string $date = '2026-01-10'): TransactionLine
    {
        $transaction = $this->transactions->post(
            header: [
                'currency_id' => $currency->id,
                'rate' => $rate,
                'date' => $date,
                'remark' => 'bill',
            ],
            lines: [
                [
                    'account_id' => $this->account('inventory-stock'),
                    'debit' => $amount,
                ],
                [
                    'account_id' => $this->account('account-payable'),
                    'ledger_id' => $this->supplier()->id,
                    'credit' => $amount,
                ],
            ]
        );

        return $transaction->lines->firstWhere('account_id', $this->account('account-payable'));
    }

    /**
     * @param  array<int, array{0: TransactionLine, 1: float}>  $applications
     */
    private function receive(float $cash, Currency $currency, float $rate, array $applications, array $extra = []): Transaction
    {
        return $this->settlements->settle(
            array_merge([
                'ledger_id' => $this->customer()->id,
                // Money IN. Set by the module, never inferred from the party.
                'direction' => SettlementService::DIRECTION_IN,
                'date' => '2026-03-01',
                'cash_account_id' => $this->account('cash-in-hand'),
                'cash_currency_id' => $currency->id,
                'cash_rate' => $rate,
                'cash_amount' => $cash,
                'remark' => 'receipt',
            ], $extra),
            collect($applications)->map(fn (array $row) => [
                'target_line_id' => $row[0]->id,
                'amount' => $row[1],
            ])->all()
        );
    }

    /**
     * @param  array<int, array{0: TransactionLine, 1: float}>  $applications
     */
    private function pay(float $cash, Currency $currency, float $rate, array $applications, array $extra = []): Transaction
    {
        return $this->settlements->settle(
            array_merge([
                'ledger_id' => $this->supplier()->id,
                // Money OUT. Set by the module, never inferred from the party.
                'direction' => SettlementService::DIRECTION_OUT,
                'date' => '2026-03-01',
                'cash_account_id' => $this->account('cash-in-hand'),
                'cash_currency_id' => $currency->id,
                'cash_rate' => $rate,
                'cash_amount' => $cash,
                'remark' => 'payment',
            ], $extra),
            collect($applications)->map(fn (array $row) => [
                'target_line_id' => $row[0]->id,
                'amount' => $row[1],
            ])->all()
        );
    }

    private function linesFor(Transaction $transaction, string $slug)
    {
        return $transaction->fresh('lines')->lines->where('account_id', $this->account($slug));
    }

    private function assertBalancedInBase(Transaction $transaction): void
    {
        $lines = $transaction->fresh('lines')->lines;

        $this->assertEqualsWithDelta(
            $lines->sum('base_debit'),
            $lines->sum('base_credit'),
            0.0001,
            'The entry must balance in base currency.'
        );
    }

    // ------------------------------------------------------
    // AFN — no FX at all
    // ------------------------------------------------------

    public function test_an_afn_settlement_emits_no_fx_line(): void
    {
        $invoice = $this->invoice(500, $this->afn(), 1);

        $receipt = $this->receive(500, $this->afn(), 1, [[$invoice, 500]]);

        $lines = $receipt->fresh('lines')->lines;

        $this->assertCount(2, $lines, 'An AFN settlement is a plain two-line voucher.');
        $this->assertCount(0, $this->linesFor($receipt, 'fx-loss'));
        $this->assertCount(0, $this->linesFor($receipt, 'fx-gain'));
        $this->assertFalse($receipt->is_cross_currency);

        $this->assertEquals(500, $this->linesFor($receipt, 'cash-in-hand')->sum('debit'));
        $this->assertEquals(500, $this->linesFor($receipt, 'account-receivable')->sum('credit'));
        $this->assertBalancedInBase($receipt);

        $this->assertSame(0.0, (float) Settlement::query()->sum('forex_amount'));
        $this->assertTrue($this->settlements->openItems($this->customer()->id)->isEmpty());
    }

    // ------------------------------------------------------
    // The headline case
    // ------------------------------------------------------

    public function test_two_hundred_dollars_booked_at_sixty_paid_at_fifty_five_realises_a_thousand_loss(): void
    {
        $usd = $this->usd();
        $invoice = $this->invoice(200, $usd, 60);

        $receipt = $this->receive(200, $usd, 55, [[$invoice, 200]]);

        $cash = $this->linesFor($receipt, 'cash-in-hand')->first();
        $this->assertEquals(200, $cash->debit);
        $this->assertEquals(55, $cash->rate);
        $this->assertEquals(11000, $cash->base_debit);

        $receivable = $this->linesFor($receipt, 'account-receivable')->first();
        $this->assertEquals(200, $receivable->credit);
        $this->assertEquals(60, $receivable->rate, 'AR is relieved at the booking rate, not the payment rate.');
        $this->assertEquals(12000, $receivable->base_credit);

        $loss = $this->linesFor($receipt, 'fx-loss')->first();
        $this->assertNotNull($loss, 'A rate drop between booking and payment is a realised loss.');
        $this->assertEquals(1000, $loss->debit);
        $this->assertEquals(1000, $loss->base_debit);
        $this->assertSame($this->afn()->id, $loss->currency_id, 'The FX line is AFN-only.');
        $this->assertEquals(1, $loss->rate);

        $this->assertBalancedInBase($receipt);

        // AR closes to zero in BOTH currencies.
        $arLines = TransactionLine::query()->where('account_id', $this->account('account-receivable'))->get();
        $this->assertEquals(0, $arLines->sum('debit') - $arLines->sum('credit'), 'AR must close in USD.');
        $this->assertEquals(0, $arLines->sum('base_debit') - $arLines->sum('base_credit'), 'AR must close in AFN.');

        $this->assertEquals(-1000, Settlement::query()->value('forex_amount'));
        $this->assertTrue($this->settlements->openItems($this->customer()->id)->isEmpty());
    }

    public function test_a_partial_payment_leaves_the_remainder_at_its_original_rate(): void
    {
        $usd = $this->usd();
        $invoice = $this->invoice(200, $usd, 60);

        $receipt = $this->receive(100, $usd, 55, [[$invoice, 100]]);

        $this->assertEquals(500, $this->linesFor($receipt, 'fx-loss')->first()?->debit);
        $this->assertBalancedInBase($receipt);

        $open = $this->settlements->openItems($this->customer()->id);

        $this->assertCount(1, $open);
        $this->assertEquals(100, $open[0]['remaining_amount']);
        // The whole point: the unpaid half is still a claim worth 60, not 55.
        $this->assertEquals(60, $open[0]['target_rate']);
    }

    public function test_a_second_payment_computes_fx_against_the_booking_rate_not_the_first_payment(): void
    {
        $usd = $this->usd();
        $invoice = $this->invoice(200, $usd, 60);

        $this->receive(100, $usd, 55, [[$invoice, 100]]);
        $second = $this->receive(100, $usd, 50, [[$invoice, 100]]);

        // 100 x (50 - 60) = -1000. Measured against 60, never against the 55
        // the earlier receipt happened to come in at.
        $this->assertEquals(1000, $this->linesFor($second, 'fx-loss')->first()?->debit);
        $this->assertEquals(60, $this->linesFor($second, 'account-receivable')->first()?->rate);
        $this->assertBalancedInBase($second);

        $this->assertTrue($this->settlements->openItems($this->customer()->id)->isEmpty());
        $this->assertEquals(-1500, Settlement::query()->sum('forex_amount'));
    }

    public function test_a_rising_rate_realises_a_gain_on_the_credit_side(): void
    {
        $usd = $this->usd();
        $invoice = $this->invoice(200, $usd, 60);

        $receipt = $this->receive(200, $usd, 65, [[$invoice, 200]]);

        $gain = $this->linesFor($receipt, 'fx-gain')->first();
        $this->assertNotNull($gain);
        $this->assertEquals(1000, $gain->credit, 'A gain is income and sits on the credit side.');
        $this->assertEquals(0, $gain->debit);
        $this->assertSame($this->afn()->id, $gain->currency_id);

        $this->assertBalancedInBase($receipt);
        $this->assertEquals(1000, Settlement::query()->value('forex_amount'));
    }

    // ------------------------------------------------------
    // Several rates on one voucher
    // ------------------------------------------------------

    public function test_three_invoices_at_three_rates_produce_three_ar_lines_and_one_net_fx_line(): void
    {
        $usd = $this->usd();
        $a = $this->invoice(100, $usd, 60, '2026-01-01');
        $b = $this->invoice(100, $usd, 58, '2026-01-02');
        $c = $this->invoice(100, $usd, 62, '2026-01-03');

        $receipt = $this->receive(300, $usd, 55, [[$a, 100], [$b, 100], [$c, 100]]);

        $arLines = $this->linesFor($receipt, 'account-receivable');

        $this->assertCount(3, $arLines, 'One AR line per distinct booking rate.');
        $this->assertEqualsCanonicalizing([58.0, 60.0, 62.0], $arLines->pluck('rate')->map(fn ($r) => (float) $r)->all());
        $this->assertEquals(300, $arLines->sum('credit'));
        $this->assertEquals(18000, $arLines->sum('base_credit'));

        $this->assertCount(1, $this->linesFor($receipt, 'fx-loss'), 'One FX line for the net, not one per document.');
        // 300 x 55 = 16,500 received against 18,000 of claims.
        $this->assertEquals(1500, $this->linesFor($receipt, 'fx-loss')->first()->debit);

        $this->assertBalancedInBase($receipt);
        $this->assertEquals(-1500, Settlement::query()->sum('forex_amount'));
        $this->assertSame(3, Settlement::query()->count());
    }

    // ------------------------------------------------------
    // Cross-currency
    // ------------------------------------------------------

    public function test_a_usd_invoice_can_be_settled_with_afn_cash(): void
    {
        $usd = $this->usd();
        $invoice = $this->invoice(200, $usd, 60);

        // 5,500 AFN settles $100 of a @60 invoice: an agreed 55, not the day's rate.
        $receipt = $this->receive(5500, $this->afn(), 1, [[$invoice, 100]], [
            'applied_cash_amount' => 5500,
        ]);

        $cash = $this->linesFor($receipt, 'cash-in-hand')->first();
        $this->assertEquals(5500, $cash->debit);
        $this->assertSame($this->afn()->id, $cash->currency_id);

        $receivable = $this->linesFor($receipt, 'account-receivable')->first();
        $this->assertEquals(100, $receivable->credit, 'The claim is relieved in its own currency.');
        $this->assertSame($usd->id, $receivable->currency_id);
        $this->assertEquals(60, $receivable->rate);
        $this->assertEquals(6000, $receivable->base_credit);

        $this->assertEquals(500, $this->linesFor($receipt, 'fx-loss')->first()?->debit);
        $this->assertBalancedInBase($receipt);

        $this->assertTrue($receipt->is_cross_currency, 'Cross-currency settlements are flagged on the voucher.');
        $this->assertTrue(Settlement::query()->value('is_cross_currency'), 'And on the settlement, so reports can split them out.');

        $open = $this->settlements->openItems($this->customer()->id);
        $this->assertEquals(100, $open[0]['remaining_amount']);
        $this->assertEquals(60, $open[0]['target_rate']);
    }

    public function test_a_cross_currency_settlement_must_state_how_much_cash_it_applies(): void
    {
        $usd = $this->usd();
        $invoice = $this->invoice(200, $usd, 60);

        $this->expectException(SettlementException::class);
        $this->expectExceptionMessage('must state how much cash is being applied');

        // Without it the system would have to invent the conversion rate the
        // customer and the business agreed on.
        $this->receive(5500, $this->afn(), 1, [[$invoice, 100]]);
    }

    // ------------------------------------------------------
    // Opening balances
    // ------------------------------------------------------

    public function test_an_opening_balance_settles_with_no_special_case(): void
    {
        $usd = $this->usd();

        // Posted the way LedgerOpeningService posts one: AR debit against
        // opening balance equity. Nothing here knows it is an opening.
        $opening = $this->transactions->post(
            header: [
                'currency_id' => $usd->id,
                'rate' => 60,
                'date' => '2026-01-01',
                'reference_type' => Ledger::class,
                'reference_id' => $this->customer()->id,
                'remark' => 'opening balance',
            ],
            lines: [
                [
                    'account_id' => $this->account('account-receivable'),
                    'ledger_id' => $this->customer()->id,
                    'debit' => 200,
                ],
                [
                    'account_id' => $this->account('opening-balance-equity'),
                    'credit' => 200,
                ],
            ]
        );

        $openingLine = $opening->lines->firstWhere('account_id', $this->account('account-receivable'));

        $open = $this->settlements->openItems($this->customer()->id);
        $this->assertCount(1, $open, 'An opening balance is an open item like any other.');
        $this->assertEquals(200, $open[0]['remaining_amount']);

        $receipt = $this->receive(200, $usd, 55, [[$openingLine, 200]]);

        $this->assertEquals(1000, $this->linesFor($receipt, 'fx-loss')->first()?->debit);
        $this->assertBalancedInBase($receipt);
        $this->assertTrue($this->settlements->openItems($this->customer()->id)->isEmpty());
    }

    // ------------------------------------------------------
    // Overpayment
    // ------------------------------------------------------

    public function test_an_overpayment_posts_the_excess_to_customer_advances(): void
    {
        $invoice = $this->invoice(500, $this->afn(), 1);

        $receipt = $this->receive(800, $this->afn(), 1, [[$invoice, 500]]);

        $this->assertEquals(800, $this->linesFor($receipt, 'cash-in-hand')->sum('debit'));
        $this->assertEquals(500, $this->linesFor($receipt, 'account-receivable')->sum('credit'));

        $advance = $this->linesFor($receipt, 'customer-advances')->first();
        $this->assertNotNull($advance, 'The excess is parked, not rejected.');
        $this->assertEquals(300, $advance->credit);
        $this->assertSame($this->customer()->id, $advance->ledger_id);

        $this->assertBalancedInBase($receipt);
        $this->assertTrue($this->settlements->openItems($this->customer()->id)->isEmpty());
    }

    public function test_allocation_preview_reports_the_unapplied_remainder(): void
    {
        $this->invoice(500, $this->afn(), 1);

        $plan = $this->settlements->allocate($this->customer()->id, $this->afn()->id, 800);

        $this->assertEquals(500, $plan['applied']);
        $this->assertEquals(300, $plan['unapplied']);
        $this->assertCount(1, $plan['allocations']);
    }

    // ------------------------------------------------------
    // Allocation strategies
    // ------------------------------------------------------

    public function test_fifo_allocates_oldest_first(): void
    {
        $usd = $this->usd();
        $this->invoice(100, $usd, 60, '2026-01-01');
        $this->invoice(100, $usd, 60, '2026-02-01');

        $plan = $this->settlements->allocate($this->customer()->id, $usd->id, 150);

        $this->assertCount(2, $plan['allocations']);
        $this->assertEquals(100, $plan['allocations'][0]['amount_applied']);
        $this->assertEquals('2026-01-01', substr((string) $plan['allocations'][0]['date'], 0, 10));
        $this->assertEquals(50, $plan['allocations'][1]['amount_applied']);
        $this->assertEquals(0, $plan['unapplied']);
    }

    public function test_manual_selection_can_skip_older_documents(): void
    {
        $usd = $this->usd();
        $this->invoice(100, $usd, 60, '2026-01-01');
        $newer = $this->invoice(100, $usd, 60, '2026-02-01');

        // "This is for invoice 254" — the customer decides, not FIFO.
        $plan = $this->settlements->allocate($this->customer()->id, $usd->id, 100, [
            ['target_line_id' => $newer->id, 'amount' => 100],
        ]);

        $this->assertCount(1, $plan['allocations']);
        $this->assertSame($newer->id, $plan['allocations'][0]['target_line_id']);
    }

    public function test_applying_more_than_is_left_is_rejected(): void
    {
        $invoice = $this->invoice(100, $this->afn(), 1);

        $this->expectException(SettlementException::class);
        $this->expectExceptionMessage('exceeds what is left');

        $this->receive(200, $this->afn(), 1, [[$invoice, 200]]);
    }

    /**
     * The case from the field: a customer with an opening balance in each
     * currency, paying both with one handful of afghanis.
     */
    public function test_one_voucher_settles_claims_in_two_currencies(): void
    {
        $usd = $this->usd();
        $afnOpening = $this->invoice(10000, $this->afn(), 1, '2026-01-01');
        $usdOpening = $this->invoice(200, $usd, 60, '2026-01-02');

        // 15,000 AFN received. 10,000 clears the afghani claim outright; the
        // remaining 5,000 is agreed to clear $83.3333 of the dollar claim at
        // its booking rate of 60.
        $receipt = $this->receive(15000, $this->afn(), 1, [
            [$afnOpening, 10000],
            [$usdOpening, 83.3333],
        ], [
            'applied_cash' => [
                ['currency_id' => $usd->id, 'amount' => 5000],
            ],
        ]);

        $this->assertEquals(15000, $this->linesFor($receipt, 'cash-in-hand')->sum('debit'));

        $receivables = $this->linesFor($receipt, 'account-receivable');
        $this->assertCount(2, $receivables, 'One receivable line per currency and rate.');

        $afnLine = $receivables->firstWhere('currency_id', $this->afn()->id);
        $this->assertEquals(10000, $afnLine->credit);
        $this->assertEquals(1, $afnLine->rate);

        // The dollar claim is relieved in DOLLARS at its own booking rate — not
        // by treating 200 afghanis as 200 dollars.
        $usdLine = $receivables->firstWhere('currency_id', $usd->id);
        $this->assertEquals(83.3333, $usdLine->credit);
        $this->assertEquals(60, $usdLine->rate);

        $this->assertBalancedInBase($receipt);
        $this->assertTrue($receipt->is_cross_currency);

        // Settling at the booking rate realises no FX beyond the rounding of
        // 83.3333 x 60 = 4,999.998 — two tenths of a fraction more cash came in
        // than the claim was worth, so it is a hair of gain. It lands in the FX
        // line and nowhere else; the receivable is relieved by exactly what was
        // applied.
        $this->assertEqualsWithDelta(0.002, $this->linesFor($receipt, 'fx-gain')->sum('credit'), 0.0001);
        $this->assertCount(0, $this->linesFor($receipt, 'fx-loss'));

        // Two settlements, each with its own rate and cross-currency flag.
        $this->assertSame(2, Settlement::query()->count());
        $afnSettlement = Settlement::query()->where('currency_id', $this->afn()->id)->firstOrFail();
        $usdSettlement = Settlement::query()->where('currency_id', $usd->id)->firstOrFail();

        $this->assertEquals(1, $afnSettlement->settlement_rate);
        $this->assertFalse($afnSettlement->is_cross_currency);
        // 5,000 / 83.3333 — a whisker over 60, because the user typed a
        // truncated 83.3333 rather than a third of a fraction. The rate is
        // recorded as what actually happened, not rounded back to a tidy 60.
        $this->assertEqualsWithDelta(60, $usdSettlement->settlement_rate, 0.0001);
        $this->assertTrue($usdSettlement->is_cross_currency);

        // The afghani claim is closed; the dollar one keeps its unpaid
        // remainder AT ITS BOOKING RATE, ready for the next payment.
        $open = $this->settlements->openItems($this->customer()->id);

        $this->assertCount(1, $open);
        $this->assertSame($usd->id, $open[0]['currency_id']);
        $this->assertEqualsWithDelta(116.6667, $open[0]['remaining_amount'], 0.0001);
        $this->assertEquals(60, $open[0]['target_rate']);
    }

    public function test_a_multi_currency_voucher_still_requires_the_cash_split(): void
    {
        $usd = $this->usd();
        $afnInvoice = $this->invoice(100, $this->afn(), 1);
        $usdInvoice = $this->invoice(100, $usd, 60);

        $this->expectException(SettlementException::class);
        $this->expectExceptionMessage('must state how much cash is being applied');

        // Without a split there is no way to know what the dollars were worth
        // to the two parties, and guessing is how a made-up gain reaches the
        // P&L.
        $this->receive(6100, $this->afn(), 1, [[$afnInvoice, 100], [$usdInvoice, 100]]);
    }

    public function test_the_scalar_shorthand_is_refused_when_two_foreign_currencies_are_settled(): void
    {
        $usd = $this->usd();

        $eur = Currency::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Euro',
            'code' => 'EUR',
            'symbol' => '€',
            'exchange_rate' => 70,
            'is_active' => true,
            'is_base_currency' => false,
            'flag' => 'eu.png',
        ]);

        $usdInvoice = $this->invoice(100, $usd, 60);
        $eurInvoice = $this->invoice(100, $eur, 70);

        $this->expectException(SettlementException::class);
        $this->expectExceptionMessage('split per currency');

        $this->receive(13000, $this->afn(), 1, [[$usdInvoice, 100], [$eurInvoice, 100]], [
            'applied_cash_amount' => 13000,
        ]);
    }

    // ------------------------------------------------------
    // The payable mirror
    // ------------------------------------------------------

    public function test_an_afn_payment_emits_no_fx_line(): void
    {
        $bill = $this->bill(500, $this->afn(), 1);

        $payment = $this->pay(500, $this->afn(), 1, [[$bill, 500]]);

        $this->assertCount(2, $payment->fresh('lines')->lines);
        $this->assertEquals(500, $this->linesFor($payment, 'cash-in-hand')->sum('credit'));
        $this->assertEquals(500, $this->linesFor($payment, 'account-payable')->sum('debit'));
        $this->assertBalancedInBase($payment);
    }

    public function test_paying_a_bill_below_its_booking_rate_realises_a_gain(): void
    {
        $usd = $this->usd();
        $bill = $this->bill(200, $usd, 60);

        $payment = $this->pay(200, $usd, 55, [[$bill, 200]]);

        $payable = $this->linesFor($payment, 'account-payable')->first();
        $this->assertEquals(200, $payable->debit);
        $this->assertEquals(60, $payable->rate, 'A payable is relieved at its booking rate too.');
        $this->assertEquals(12000, $payable->base_debit);

        $gain = $this->linesFor($payment, 'fx-gain')->first();
        $this->assertNotNull($gain, 'Settling a 12,000 debt with 11,000 of cash is a gain.');
        $this->assertEquals(1000, $gain->credit);

        $this->assertBalancedInBase($payment);
        $this->assertEquals(1000, Settlement::query()->value('forex_amount'), 'Positive is a gain on both sides.');
    }

    public function test_paying_a_bill_above_its_booking_rate_realises_a_loss(): void
    {
        $usd = $this->usd();
        $bill = $this->bill(200, $usd, 60);

        $payment = $this->pay(200, $usd, 65, [[$bill, 200]]);

        $this->assertEquals(1000, $this->linesFor($payment, 'fx-loss')->first()?->debit);
        $this->assertBalancedInBase($payment);
        $this->assertEquals(-1000, Settlement::query()->value('forex_amount'));
    }

    public function test_a_partial_payment_to_a_supplier_keeps_the_remainder_at_its_rate(): void
    {
        $usd = $this->usd();
        $bill = $this->bill(200, $usd, 60);

        $this->pay(100, $usd, 55, [[$bill, 100]]);
        // Money OUT: a supplier's bills are AP credits.
        $open = $this->settlements->openItems(
            $this->supplier()->id,
            null,
            SettlementService::DIRECTION_OUT
        );

        $this->assertCount(1, $open);
        $this->assertEquals(100, $open[0]['remaining_amount']);
        $this->assertEquals(60, $open[0]['target_rate']);

        $second = $this->pay(100, $usd, 50, [[$bill, 100]]);

        // 100 x (60 - 50) = 1,000 gain, measured against 60 not 55.
        $this->assertEquals(1000, $this->linesFor($second, 'fx-gain')->first()?->credit);
        $this->assertEquals(60, $this->linesFor($second, 'account-payable')->first()?->rate);
    }

    public function test_three_bills_at_three_rates_produce_three_ap_lines_and_one_net_fx_line(): void
    {
        $usd = $this->usd();
        $a = $this->bill(100, $usd, 60, '2026-01-01');
        $b = $this->bill(100, $usd, 58, '2026-01-02');
        $c = $this->bill(100, $usd, 62, '2026-01-03');

        $payment = $this->pay(300, $usd, 55, [[$a, 100], [$b, 100], [$c, 100]]);

        $apLines = $this->linesFor($payment, 'account-payable');

        $this->assertCount(3, $apLines);
        $this->assertEquals(300, $apLines->sum('debit'));
        $this->assertEquals(18000, $apLines->sum('base_debit'));
        $this->assertCount(1, $this->linesFor($payment, 'fx-gain'));
        $this->assertEquals(1500, $this->linesFor($payment, 'fx-gain')->first()->credit);
        $this->assertBalancedInBase($payment);
    }

    public function test_a_supplier_overpayment_posts_to_supplier_advances(): void
    {
        $bill = $this->bill(500, $this->afn(), 1);

        $payment = $this->pay(800, $this->afn(), 1, [[$bill, 500]]);

        $advance = $this->linesFor($payment, 'supplier-advances')->first();
        $this->assertNotNull($advance);
        $this->assertEquals(300, $advance->debit, 'Money paid ahead of a bill is an asset.');
        $this->assertBalancedInBase($payment);
    }

    public function test_a_bill_can_be_settled_with_afn_cash(): void
    {
        $usd = $this->usd();
        $bill = $this->bill(200, $usd, 60);

        $payment = $this->pay(5500, $this->afn(), 1, [[$bill, 100]], [
            'applied_cash_amount' => 5500,
        ]);

        $payable = $this->linesFor($payment, 'account-payable')->first();
        $this->assertEquals(100, $payable->debit);
        $this->assertSame($usd->id, $payable->currency_id);
        $this->assertEquals(6000, $payable->base_debit);

        // 6,000 of debt cleared with 5,500 of cash.
        $this->assertEquals(500, $this->linesFor($payment, 'fx-gain')->first()?->credit);
        $this->assertTrue($payment->is_cross_currency);
        $this->assertBalancedInBase($payment);
    }

    // ------------------------------------------------------
    // Rounding and integrity
    // ------------------------------------------------------

    public function test_rounding_remainders_land_in_fx_and_never_in_the_receivable(): void
    {
        $usd = $this->usd();
        // Rates chosen so amount x rate does not land on a clean fraction.
        $a = $this->invoice(33.3333, $usd, 60.123456, '2026-01-01');
        $b = $this->invoice(66.6667, $usd, 58.987654, '2026-01-02');

        $receipt = $this->receive(100, $usd, 55.555555, [[$a, 33.3333], [$b, 66.6667]]);

        $arLines = $this->linesFor($receipt, 'account-receivable');

        // AR is relieved by exactly what was applied, to the last fraction.
        $this->assertEquals(100, $arLines->sum('credit'));
        $this->assertBalancedInBase($receipt);

        // And the settlements rows add up to the posted FX line.
        $posted = $this->linesFor($receipt, 'fx-loss')->sum('debit')
            - $this->linesFor($receipt, 'fx-gain')->sum('credit');

        $this->assertEqualsWithDelta(
            -$posted,
            (float) Settlement::query()->sum('forex_amount'),
            0.0001,
            'The subledger must agree with the general ledger to the last fraction.'
        );

        $this->assertTrue($this->settlements->openItems($this->customer()->id)->isEmpty());
    }

    public function test_settlement_rows_record_both_rates_and_the_relieved_base(): void
    {
        $usd = $this->usd();
        $invoice = $this->invoice(200, $usd, 60);

        $receipt = $this->receive(200, $usd, 55, [[$invoice, 200]]);

        $settlement = Settlement::query()->first();

        $this->assertSame($receipt->id, $settlement->transaction_id);
        $this->assertSame($invoice->id, $settlement->target_line_id);
        $this->assertSame($this->customer()->id, $settlement->ledger_id);
        $this->assertSame($usd->id, $settlement->currency_id, 'amount_applied is in the target currency.');
        $this->assertEquals(200, $settlement->amount_applied);
        $this->assertEquals(60, $settlement->target_rate);
        $this->assertEquals(55, $settlement->settlement_rate);
        $this->assertEquals(12000, $settlement->base_relieved);
        $this->assertEquals(-1000, $settlement->forex_amount);

        // The settling line is the AR credit this voucher posted.
        $this->assertSame(
            $this->linesFor($receipt, 'account-receivable')->first()->id,
            $settlement->settling_line_id
        );
    }

    public function test_the_same_pair_cannot_be_settled_twice(): void
    {
        $invoice = $this->invoice(500, $this->afn(), 1);
        $receipt = $this->receive(200, $this->afn(), 1, [[$invoice, 200]]);

        $settlement = Settlement::query()->first();

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('settlements')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'transaction_id' => $receipt->id,
            'settling_line_id' => $settlement->settling_line_id,
            'target_line_id' => $settlement->target_line_id,
            'ledger_id' => $settlement->ledger_id,
            'currency_id' => $settlement->currency_id,
            'amount_applied' => 1,
            'target_rate' => 1,
            'settlement_rate' => 1,
            'base_relieved' => 1,
            'forex_amount' => 0,
            'branch_id' => $this->ctx['branch']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_fully_settled_document_drops_out_of_open_items(): void
    {
        $usd = $this->usd();
        $invoice = $this->invoice(200, $usd, 60);

        $this->assertCount(1, $this->settlements->openItems($this->customer()->id, $usd->id));

        $this->receive(200, $usd, 60, [[$invoice, 200]]);

        $this->assertCount(0, $this->settlements->openItems($this->customer()->id, $usd->id));
    }

    public function test_open_items_can_be_filtered_to_one_currency(): void
    {
        $usd = $this->usd();
        $this->invoice(100, $usd, 60);
        $this->invoice(100, $this->afn(), 1);

        $this->assertCount(2, $this->settlements->openItems($this->customer()->id));
        $this->assertCount(1, $this->settlements->openItems($this->customer()->id, $usd->id));
    }
}

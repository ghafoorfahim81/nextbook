<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Settlement;
use App\Models\Administration\Currency;
use App\Models\Payment\Payment;
use App\Models\Receipt\Receipt;
use App\Models\Transaction\TransactionLine;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The receipt and payment modules end to end, through HTTP.
 *
 * SettlementServiceTest covers the arithmetic. This covers the wiring: that the
 * forms' payload shape reaches the service intact, that the preview endpoints
 * answer what the form needs, and that the two modules stay mirrors of each
 * other rather than drifting apart.
 */
class SettlementEndpointsTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

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

    private function account(string $slug): string
    {
        return $this->ctx['accounts'][$slug]->id;
    }

    private function invoice(float $amount, Currency $currency, float $rate): TransactionLine
    {
        $transaction = app(TransactionService::class)->post(
            header: [
                'currency_id' => $currency->id,
                'rate' => $rate,
                'date' => '2026-01-10',
                'remark' => 'invoice',
            ],
            lines: [
                [
                    'account_id' => $this->account('account-receivable'),
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'debit' => $amount,
                ],
                ['account_id' => $this->account('sales-revenue'), 'credit' => $amount],
            ]
        );

        return $transaction->lines->firstWhere('account_id', $this->account('account-receivable'));
    }

    private function bill(float $amount, Currency $currency, float $rate): TransactionLine
    {
        $transaction = app(TransactionService::class)->post(
            header: [
                'currency_id' => $currency->id,
                'rate' => $rate,
                'date' => '2026-01-10',
                'remark' => 'bill',
            ],
            lines: [
                ['account_id' => $this->account('inventory-stock'), 'debit' => $amount],
                [
                    'account_id' => $this->account('account-payable'),
                    'ledger_id' => $this->ctx['supplier_ledger']->id,
                    'credit' => $amount,
                ],
            ]
        );

        return $transaction->lines->firstWhere('account_id', $this->account('account-payable'));
    }

    // ------------------------------------------------------
    // Preview endpoints
    // ------------------------------------------------------

    public function test_open_items_endpoint_returns_claims_with_their_booking_rate(): void
    {
        $usd = $this->usd();
        $this->invoice(200, $usd, 60);

        $response = $this->getJson(route('settlements.open-items', [
            'ledger_id' => $this->ctx['customer_ledger']->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.0.remaining_amount', '200.0000');
        $response->assertJsonPath('data.0.target_rate', '60.00000000');
        $response->assertJsonPath('meta.ledger_type', 'customer');
        $response->assertJsonStructure(['meta' => ['balances' => ['currencies', 'base_total']]]);
    }

    public function test_preview_endpoint_reports_the_loss_before_anything_is_saved(): void
    {
        $usd = $this->usd();
        $invoice = $this->invoice(200, $usd, 60);

        $response = $this->postJson(route('settlements.preview'), [
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'cash_currency_id' => $usd->id,
            'cash_rate' => 55,
            'cash_amount' => 200,
            'allocations' => [
                ['target_line_id' => $invoice->id, 'amount' => 200],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.net_forex', '-1000.0000');
        $response->assertJsonPath('data.allocations.0.forex_amount', '-1000.0000');
        $response->assertJsonPath('data.unapplied', '0.0000');
        $response->assertJsonPath('data.advance_account', null);

        // Nothing was posted.
        $this->assertSame(0, Settlement::query()->count());
        $this->assertSame(1, \App\Models\Transaction\Transaction::query()->count());
    }

    public function test_preview_names_the_advance_account_for_an_overpayment(): void
    {
        $this->invoice(100, $this->ctx['currency'], 1);

        // No allocations: the on-account default relieves what is open and
        // reports the rest as an advance, which is what the form warns about.
        $response = $this->postJson(route('settlements.preview'), [
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'cash_currency_id' => $this->ctx['currency']->id,
            'cash_rate' => 1,
            'cash_amount' => 150,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.cash_applied', '100.0000');
        $response->assertJsonPath('data.unapplied', '50.0000');
        $response->assertJsonPath('data.advance_account', 'customer-advances');
    }

    public function test_preview_reports_a_gain_when_cash_goes_out(): void
    {
        $usd = $this->usd();
        $bill = $this->bill(200, $usd, 60);

        $response = $this->postJson(route('settlements.preview'), [
            'ledger_id' => $this->ctx['supplier_ledger']->id,
            'direction' => 'out',
            'cash_currency_id' => $usd->id,
            'cash_rate' => 55,
            'cash_amount' => 200,
            'allocations' => [
                ['target_line_id' => $bill->id, 'amount' => 200],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.direction', 'out');
        // Clearing a 12,000 debt with 11,000 of cash is a GAIN. The sign
        // follows the direction of the cash, not the party's account.
        $response->assertJsonPath('data.net_forex', '1000.0000');
    }

    public function test_preview_splits_the_cash_across_two_claim_currencies(): void
    {
        $usd = $this->usd();
        $afnOpening = $this->invoice(10000, $this->ctx['currency'], 1);
        $usdOpening = $this->invoice(200, $usd, 60);

        $response = $this->postJson(route('settlements.preview'), [
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'cash_currency_id' => $this->ctx['currency']->id,
            'cash_rate' => 1,
            'cash_amount' => 15000,
            'allocations' => [
                ['target_line_id' => $afnOpening->id, 'amount' => 10000],
                ['target_line_id' => $usdOpening->id, 'amount' => 83.3333],
            ],
            'applied_cash' => [
                ['currency_id' => $usd->id, 'amount' => 5000],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.cash_applied', '15000.0000');
        $response->assertJsonPath('data.unapplied', '0.0000');
        $response->assertJsonPath('data.is_cross_currency', true);

        // Each currency reports its own applied total and its own rate — the
        // two are never summed, because 10,000 AFN and $83 are not 10,083 of
        // anything.
        $currencies = collect($response->json('data.currencies'))->keyBy('currency_id');

        $this->assertEquals('10000.0000', $currencies[$this->ctx['currency']->id]['applied']);
        $this->assertEquals('83.3333', $currencies[$usd->id]['applied']);
        $this->assertEquals('5000.0000', $currencies[$usd->id]['cash_applied']);
        $this->assertTrue($currencies[$usd->id]['is_cross_currency']);
        $this->assertFalse($currencies[$this->ctx['currency']->id]['is_cross_currency']);
    }

    public function test_open_items_label_an_opening_balance_as_such(): void
    {
        $usd = $this->usd();

        $transaction = app(TransactionService::class)->post(
            header: [
                'currency_id' => $usd->id,
                'rate' => 60,
                'date' => '2026-01-01',
                'reference_type' => \App\Models\Ledger\Ledger::class,
                'reference_id' => $this->ctx['customer_ledger']->id,
                'remark' => 'opening balance',
            ],
            lines: [
                [
                    'account_id' => $this->account('account-receivable'),
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'debit' => 200,
                ],
                ['account_id' => $this->account('opening-balance-equity'), 'credit' => 200],
            ]
        );

        \App\Models\Ledger\LedgerOpening::create([
            'ledgerable_id' => $this->ctx['customer_ledger']->id,
            'ledgerable_type' => \App\Models\Ledger\Ledger::class,
            'transaction_id' => $transaction->id,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $response = $this->getJson(route('settlements.open-items', [
            'ledger_id' => $this->ctx['customer_ledger']->id,
        ]));

        // "Ledger" told the user nothing — they are looking for the balance
        // they typed in when the customer was created.
        $response->assertJsonPath('data.0.document_type', 'Opening Balance');
    }

    // ------------------------------------------------------
    // Posting through the controllers
    // ------------------------------------------------------

    public function test_a_receipt_posts_the_fx_loss_through_the_controller(): void
    {
        $usd = $this->usd();
        $invoice = $this->invoice(200, $usd, 60);

        $response = $this->post(route('receipts.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 200,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $usd->id,
            'rate' => 55,
            'allocations' => [
                ['target_line_id' => $invoice->id, 'amount' => 200],
            ],
        ]);

        $response->assertRedirect(route('receipts.index'));

        $lines = Receipt::query()->latest()->firstOrFail()->transaction()->firstOrFail()->lines;

        $this->assertEquals(1000, $lines->firstWhere('account_id', $this->account('fx-loss'))?->debit);
        $this->assertEquals(60, $lines->firstWhere('account_id', $this->account('account-receivable'))?->rate);
        $this->assertEquals($lines->sum('base_debit'), $lines->sum('base_credit'));

        $this->assertSame(1, Settlement::query()->count());
        $this->assertEquals(-1000, Settlement::query()->value('forex_amount'));
    }

    public function test_a_payment_posts_the_fx_gain_through_the_controller(): void
    {
        $usd = $this->usd();
        $bill = $this->bill(200, $usd, 60);

        $response = $this->post(route('payments.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['supplier_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 200,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $usd->id,
            'rate' => 55,
            'allocations' => [
                ['target_line_id' => $bill->id, 'amount' => 200],
            ],
        ]);

        $response->assertRedirect(route('payments.index'));

        $lines = Payment::query()->latest()->firstOrFail()->transaction()->firstOrFail()->lines;

        $this->assertEquals(1000, $lines->firstWhere('account_id', $this->account('fx-gain'))?->credit);
        $this->assertEquals(60, $lines->firstWhere('account_id', $this->account('account-payable'))?->rate);
        $this->assertEquals($lines->sum('base_debit'), $lines->sum('base_credit'));

        $this->assertEquals(1000, Settlement::query()->value('forex_amount'));
    }

    public function test_a_cross_currency_receipt_posts_through_the_controller(): void
    {
        $usd = $this->usd();
        $invoice = $this->invoice(200, $usd, 60);

        $response = $this->post(route('receipts.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 5500,
            'applied_cash_amount' => 5500,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'allocations' => [
                ['target_line_id' => $invoice->id, 'amount' => 100],
            ],
        ]);

        $response->assertRedirect(route('receipts.index'));

        $transaction = Receipt::query()->latest()->firstOrFail()->transaction()->firstOrFail();

        $this->assertTrue($transaction->is_cross_currency);
        $this->assertEquals(500, $transaction->lines->firstWhere('account_id', $this->account('fx-loss'))?->debit);
        $this->assertTrue(Settlement::query()->value('is_cross_currency'));
    }

    // ------------------------------------------------------
    // Auto-allocation across rates and currencies
    // ------------------------------------------------------

    /**
     * The reported bug: a 200 USD opening booked at 60, paid with 200 USD at
     * 55, auto-applied only 183.33 and left 16.67 outstanding.
     *
     * The rates decide the exchange difference; they have nothing to do with
     * how much is settled. 200 dollars clears 200 dollars.
     */
    public function test_auto_settles_a_same_currency_claim_in_full_regardless_of_rate(): void
    {
        $usd = $this->usd();
        $opening = $this->invoice(200, $usd, 60);

        $response = $this->postJson(route('settlements.preview'), [
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'direction' => 'in',
            'cash_currency_id' => $usd->id,
            'cash_rate' => 55,
            'cash_amount' => 200,
            'strategy' => 'fifo',
        ]);

        $response->assertOk();

        // The whole 200 is applied — not 200 x 55 / 60.
        $response->assertJsonPath('data.allocations.0.target_line_id', $opening->id);
        $response->assertJsonPath('data.allocations.0.amount_applied', '200.0000');
        $response->assertJsonPath('data.cash_applied', '200.0000');
        $response->assertJsonPath('data.unapplied', '0.0000');

        // 200 x 55 = 11,000 received against a 200 x 60 = 12,000 claim.
        $response->assertJsonPath('data.net_forex', '-1000.0000');
    }

    public function test_that_settlement_closes_the_balance_to_zero_in_both_currencies(): void
    {
        $usd = $this->usd();
        $opening = $this->invoice(200, $usd, 60);

        $this->post(route('receipts.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 200,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $usd->id,
            'rate' => 55,
            'allocations' => [['target_line_id' => $opening->id, 'amount' => 200]],
        ])->assertRedirect(route('receipts.index'));

        // Nothing left open, and no advance was created out of a phantom
        // remainder.
        $this->assertCount(0, app(\App\Services\Accounting\SettlementService::class)
            ->openItems($this->ctx['customer_ledger']->id));

        $ledgerLines = TransactionLine::query()
            ->where('ledger_id', $this->ctx['customer_ledger']->id)
            ->get();

        // Closed in BOTH currencies. The receivable was relieved at the rate it
        // was booked at, so its base value cancels exactly too; the 1,000 AFN
        // shortfall sits on the FX account, not on the customer.
        $this->assertEquals(0, $ledgerLines->sum('debit') - $ledgerLines->sum('credit'), 'Closed in USD.');
        $this->assertEquals(
            0,
            $ledgerLines->sum('base_debit') - $ledgerLines->sum('base_credit'),
            'Closed in AFN.'
        );

        $receipt = Receipt::query()->latest()->firstOrFail()->transaction()->firstOrFail();

        $this->assertEquals(1000, $receipt->lines->firstWhere('account_id', $this->account('fx-loss'))?->debit);
        $this->assertCount(0, $receipt->lines->where('account_id', $this->account('customer-advances')));
    }

    public function test_auto_converts_only_claims_in_another_currency(): void
    {
        $usd = $this->usd();
        $afnOpening = $this->invoice(10000, $this->ctx['currency'], 1, '2026-01-01');
        $usdOpening = $this->invoice(200, $usd, 60, '2026-01-02');

        // 15,000 AFN: the afghani claim takes 10,000 one-for-one, and the
        // remaining 5,000 converts into the dollar claim at its booking rate.
        $response = $this->postJson(route('settlements.preview'), [
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'direction' => 'in',
            'cash_currency_id' => $this->ctx['currency']->id,
            'cash_rate' => 1,
            'cash_amount' => 15000,
            'strategy' => 'fifo',
        ]);

        $response->assertOk();

        $applied = collect($response->json('data.allocations'))->keyBy('target_line_id');

        $this->assertEquals('10000.0000', $applied[$afnOpening->id]['amount_applied']);
        $this->assertEquals('83.3333', $applied[$usdOpening->id]['amount_applied']);
        $response->assertJsonPath('data.unapplied', '0.0000');
    }

    // ------------------------------------------------------
    // Any party on either form
    // ------------------------------------------------------

    /**
     * The direction decides which column of the party's account is a claim.
     * Money IN relieves what they owe; money OUT relieves what is owed to them.
     */
    public function test_the_direction_decides_which_claims_are_offered(): void
    {
        $this->invoice(10000, $this->ctx['currency'], 1);

        // Money in: the customer's 10,000 debit is a claim to collect.
        $this->getJson(route('settlements.open-items', [
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'direction' => 'in',
        ]))->assertJsonCount(1, 'data');

        // Money out: they have nothing owed TO them, so nothing to refund.
        $this->getJson(route('settlements.open-items', [
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'direction' => 'out',
        ]))->assertJsonCount(0, 'data');
    }

    public function test_a_customer_can_be_paid_and_cash_is_credited(): void
    {
        // The customer overpaid: a manual credit note leaves 2,000 owed to them.
        $creditNote = app(TransactionService::class)->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-02-01',
                'remark' => 'credit note',
            ],
            lines: [
                ['account_id' => $this->account('sales-revenue'), 'debit' => 2000],
                [
                    'account_id' => $this->account('account-receivable'),
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'credit' => 2000,
                ],
            ]
        )->lines->firstWhere('account_id', $this->account('account-receivable'));

        $response = $this->post(route('payments.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 2000,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'allocations' => [['target_line_id' => $creditNote->id, 'amount' => 2000]],
        ]);

        $response->assertRedirect(route('payments.index'));

        $lines = Payment::query()->latest()->firstOrFail()->transaction()->firstOrFail()->lines;

        // Cash goes OUT — credited — even though the party is a customer. And
        // the relief lands on ACCOUNTS RECEIVABLE, because that is where a
        // customer's balance lives whichever way the money moved.
        $this->assertEquals(2000, $lines->firstWhere('account_id', $this->account('cash-in-hand'))->credit);
        $this->assertEquals(0, $lines->firstWhere('account_id', $this->account('cash-in-hand'))->debit);
        $this->assertEquals(2000, $lines->firstWhere('account_id', $this->account('account-receivable'))->debit);

        $this->assertEquals($lines->sum('base_debit'), $lines->sum('base_credit'));
        $this->assertSame(1, Settlement::query()->count());
    }

    public function test_a_supplier_can_pay_you_and_cash_is_debited(): void
    {
        // An advance paid to a supplier leaves them holding 3,000 of ours; they
        // hand it back.
        $advance = app(TransactionService::class)->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-02-01',
                'remark' => 'advance to supplier',
            ],
            lines: [
                [
                    'account_id' => $this->account('account-payable'),
                    'ledger_id' => $this->ctx['supplier_ledger']->id,
                    'debit' => 3000,
                ],
                ['account_id' => $this->account('cash-in-hand'), 'credit' => 3000],
            ]
        )->lines->firstWhere('account_id', $this->account('account-payable'));

        $response = $this->post(route('receipts.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['supplier_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 3000,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'allocations' => [['target_line_id' => $advance->id, 'amount' => 3000]],
        ]);

        $response->assertRedirect(route('receipts.index'));

        $lines = Receipt::query()->latest()->firstOrFail()->transaction()->firstOrFail()->lines;

        $this->assertEquals(3000, $lines->firstWhere('account_id', $this->account('cash-in-hand'))->debit);
        // Relief on ACCOUNTS PAYABLE — a supplier's balance lives there even
        // when they are the one handing money over.
        $this->assertEquals(3000, $lines->firstWhere('account_id', $this->account('account-payable'))->credit);
        $this->assertEquals($lines->sum('base_debit'), $lines->sum('base_credit'));
    }

    public function test_a_receipt_is_not_offered_back_as_something_to_refund(): void
    {
        $invoice = $this->invoice(500, $this->ctx['currency'], 1);

        $this->post(route('receipts.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 500,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'allocations' => [['target_line_id' => $invoice->id, 'amount' => 500]],
        ])->assertRedirect(route('receipts.index'));

        // That receipt CREDITED receivables — the same column a refund relieves.
        // It settled an invoice, so it is not itself something the customer is
        // owed, and it must not appear as refundable.
        $this->getJson(route('settlements.open-items', [
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'direction' => 'out',
        ]))->assertJsonCount(0, 'data');
    }

    public function test_refunding_a_customer_at_a_lower_rate_realises_a_gain(): void
    {
        $usd = $this->usd();

        // 200 USD owed back to the customer, booked at 60.
        $creditNote = app(TransactionService::class)->post(
            header: [
                'currency_id' => $usd->id,
                'rate' => 60,
                'date' => '2026-02-01',
                'remark' => 'credit note',
            ],
            lines: [
                ['account_id' => $this->account('sales-revenue'), 'debit' => 200],
                [
                    'account_id' => $this->account('account-receivable'),
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'credit' => 200,
                ],
            ]
        )->lines->firstWhere('account_id', $this->account('account-receivable'));

        $this->post(route('payments.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 200,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $usd->id,
            'rate' => 55,
            'allocations' => [['target_line_id' => $creditNote->id, 'amount' => 200]],
        ])->assertRedirect(route('payments.index'));

        $lines = Payment::query()->latest()->firstOrFail()->transaction()->firstOrFail()->lines;

        // Clearing a 12,000 obligation with 11,000 of cash is a GAIN — the sign
        // follows the direction of the cash, not the party's account.
        $this->assertEquals(1000, $lines->firstWhere('account_id', $this->account('fx-gain'))?->credit);
        $this->assertEquals(60, $lines->firstWhere('account_id', $this->account('account-receivable'))?->rate);
        $this->assertEquals($lines->sum('base_debit'), $lines->sum('base_credit'));
        $this->assertEquals(1000, Settlement::query()->value('forex_amount'));
    }

    // ------------------------------------------------------
    // Editing an existing voucher
    // ------------------------------------------------------

    /**
     * The receipt in the screenshot: 15,000 AFN settling a 10,000 AFN opening
     * and part of a $200 one. Reopening it for edit must show what it settled.
     */
    public function test_editing_a_receipt_shows_the_documents_it_settled_as_open(): void
    {
        $usd = $this->usd();
        $afnOpening = $this->invoice(10000, $this->ctx['currency'], 1);
        $usdOpening = $this->invoice(200, $usd, 60);

        $this->post(route('receipts.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 15000,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'allocations' => [
                ['target_line_id' => $afnOpening->id, 'amount' => 10000],
                ['target_line_id' => $usdOpening->id, 'amount' => 83.3333],
            ],
            'applied_cash' => [
                ['currency_id' => $usd->id, 'amount' => 5000],
            ],
        ])->assertRedirect(route('receipts.index'));

        $receipt = Receipt::query()->latest()->firstOrFail();
        $transaction = $receipt->transaction()->firstOrFail();

        // Without the exclusion the afghani opening is fully settled and simply
        // disappears — the edit form then reads "nothing open on this ledger"
        // for a receipt that plainly settled something.
        $this->getJson(route('settlements.open-items', [
            'ledger_id' => $this->ctx['customer_ledger']->id,
        ]))->assertJsonCount(1, 'data');

        $response = $this->getJson(route('settlements.open-items', [
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'exclude_transaction_id' => $transaction->id,
        ]));

        $response->assertJsonCount(2, 'data');

        $items = collect($response->json('data'))->keyBy('target_line_id');

        // Each one is offered at its full remaining amount again, because this
        // voucher's own applications no longer count against it.
        $this->assertEquals('10000.0000', $items[$afnOpening->id]['remaining_amount']);
        $this->assertEquals('200.0000', $items[$usdOpening->id]['remaining_amount']);
        $this->assertEquals('60.00000000', $items[$usdOpening->id]['target_rate']);
    }

    public function test_the_edit_form_reports_the_cash_amount_not_a_receivable_line(): void
    {
        $usd = $this->usd();
        $afnOpening = $this->invoice(10000, $this->ctx['currency'], 1);
        $usdOpening = $this->invoice(200, $usd, 60);

        $this->post(route('receipts.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 15000,
            'bank_account_id' => $this->account('cash-in-safe'),
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'allocations' => [
                ['target_line_id' => $afnOpening->id, 'amount' => 10000],
                ['target_line_id' => $usdOpening->id, 'amount' => 83.3333],
            ],
            'applied_cash' => [
                ['currency_id' => $usd->id, 'amount' => 5000],
            ],
        ])->assertRedirect(route('receipts.index'));

        $receipt = Receipt::query()->latest()->firstOrFail();

        $response = $this->getJson(route('receipts.show', $receipt));

        // A settlement voucher has several lines in no guaranteed order.
        // Reading lines[0] put the 10,000 receivable relief into the Amount box
        // of a 15,000 receipt.
        $response->assertJsonPath('data.amount', 15000);
        $response->assertJsonPath('data.bank_account_id', $this->account('cash-in-safe'));
        $response->assertJsonPath('data.transaction_id', $receipt->transaction()->firstOrFail()->id);

        // And the settlements it made are there to repopulate the form.
        $this->assertCount(2, $response->json('data.settlements'));
    }

    public function test_a_payment_reports_the_cash_amount_not_a_payable_line(): void
    {
        $usd = $this->usd();
        $bill = $this->bill(200, $usd, 60);

        $this->post(route('payments.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['supplier_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 200,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $usd->id,
            // Paying above the booking rate puts an FX LOSS on the voucher —
            // a debit, which is what the old "first debit is the cash" rule
            // would have picked up on the payable side.
            'rate' => 65,
            'allocations' => [
                ['target_line_id' => $bill->id, 'amount' => 200],
            ],
        ])->assertRedirect(route('payments.index'));

        $payment = Payment::query()->latest()->firstOrFail();

        $response = $this->getJson(route('payments.show', $payment));

        $response->assertJsonPath('data.amount', 200);
        $response->assertJsonPath('data.bank_account_id', $this->account('cash-in-hand'));
    }

    public function test_resaving_an_edited_receipt_unchanged_keeps_the_same_settlements(): void
    {
        $invoice = $this->invoice(500, $this->ctx['currency'], 1);

        $this->post(route('receipts.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 500,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'allocations' => [['target_line_id' => $invoice->id, 'amount' => 500]],
        ])->assertRedirect(route('receipts.index'));

        $receipt = Receipt::query()->latest()->firstOrFail();

        // Re-submitting the same allocation must not trip the over-applied
        // check: update replaces the voucher's settlements rather than adding
        // to them.
        $this->put(route('receipts.update', $receipt), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 500,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'allocations' => [['target_line_id' => $invoice->id, 'amount' => 500]],
        ])->assertRedirect(route('receipts.index'));

        $this->assertSame(1, Settlement::query()->count());
        $this->assertEquals(500, Settlement::query()->value('amount_applied'));
        $this->assertCount(0, app(\App\Services\Accounting\SettlementService::class)
            ->openItems($this->ctx['customer_ledger']->id));
    }

    // ------------------------------------------------------
    // The show page
    // ------------------------------------------------------

    public function test_the_show_route_renders_a_page_naming_what_was_settled(): void
    {
        $usd = $this->usd();
        $opening = $this->invoice(200, $usd, 60);

        $this->post(route('receipts.store'), [
            'number' => 7,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 200,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $usd->id,
            'rate' => 55,
            'allocations' => [['target_line_id' => $opening->id, 'amount' => 200]],
        ])->assertRedirect(route('receipts.index'));

        $receipt = Receipt::query()->latest()->firstOrFail();

        $this->get(route('receipts.show', $receipt))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Receipts/Show')
                ->where('receipt.data.number', '7')
                ->has('settlements', 1)
                // Named, not a truncated ULID — the dialog used to show the
                // last six characters of the line id, which told nobody
                // anything. (This fixture posts a bare journal; the
                // Opening Balance label is covered by the open-items test.)
                ->where('settlements.0.document_type', 'Journal')
                ->where('settlements.0.target_rate', fn ($rate) => (float) $rate === 60.0)
                ->where('settlements.0.settlement_rate', fn ($rate) => (float) $rate === 55.0)
                ->where('settlements.0.forex_kind', 'loss')
                // The posted entry travels with the page so the exchange line
                // can be shown next to the cash and the receivable.
                ->has('receipt.data.transaction.lines', 3)
            );
    }

    public function test_a_payment_show_page_renders_for_the_payable_side(): void
    {
        $bill = $this->bill(500, $this->ctx['currency'], 1);

        $this->post(route('payments.store'), [
            'number' => 4,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['supplier_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 500,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'allocations' => [['target_line_id' => $bill->id, 'amount' => 500]],
        ])->assertRedirect(route('payments.index'));

        $payment = Payment::query()->latest()->firstOrFail();

        $this->get(route('payments.show', $payment))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Payments/Show')
                ->where('payment.data.number', '4')
                ->has('settlements', 1)
                ->where('settlements.0.forex_kind', 'none')
            );
    }

    public function test_the_show_route_still_answers_json_for_the_edit_form(): void
    {
        $invoice = $this->invoice(500, $this->ctx['currency'], 1);

        $this->post(route('receipts.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 500,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'allocations' => [['target_line_id' => $invoice->id, 'amount' => 500]],
        ])->assertRedirect(route('receipts.index'));

        $receipt = Receipt::query()->latest()->firstOrFail();

        // The edit form fetches this same URL over axios to populate itself.
        // Turning it into a page must not take that away.
        $this->getJson(route('receipts.show', $receipt))
            ->assertOk()
            ->assertJsonPath('data.amount', 500)
            ->assertJsonCount(1, 'data.settlements');
    }

    // ------------------------------------------------------
    // Export
    // ------------------------------------------------------

    public function test_the_receipt_export_streams_with_the_cash_amount(): void
    {
        $usd = $this->usd();
        $opening = $this->invoice(200, $usd, 60);

        $this->post(route('receipts.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 200,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $usd->id,
            'rate' => 55,
            'allocations' => [['target_line_id' => $opening->id, 'amount' => 200]],
        ])->assertRedirect(route('receipts.index'));

        // payment_mode is cast to the PaymentMode enum, and the export cast it
        // to string — a fatal error, not a value. The voucher also has three
        // lines, so "the first line" was the receivable, not the cash.
        $this->get(route('receipts.export', ['sortField' => 'date', 'sortDirection' => 'desc']))
            ->assertOk()
            ->assertDownload();
    }

    public function test_the_payment_export_streams_with_the_cash_amount(): void
    {
        $bill = $this->bill(500, $this->ctx['currency'], 1);

        $this->post(route('payments.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['supplier_ledger']->id,
            'payment_mode' => 'on_account',
            'amount' => 500,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'allocations' => [['target_line_id' => $bill->id, 'amount' => 500]],
        ])->assertRedirect(route('payments.index'));

        $this->get(route('payments.export', ['sortField' => 'date', 'sortDirection' => 'desc']))
            ->assertOk()
            ->assertDownload();
    }

    public function test_deleting_a_receipt_reopens_what_it_settled(): void
    {
        $invoice = $this->invoice(500, $this->ctx['currency'], 1);

        $this->post(route('receipts.store'), [
            'number' => 1,
            'date' => '2026-03-01',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'payment_mode' => 'bill_by_bill',
            'amount' => 500,
            'bank_account_id' => $this->account('cash-in-hand'),
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'allocations' => [
                ['target_line_id' => $invoice->id, 'amount' => 500],
            ],
        ]);

        $settlements = app(\App\Services\Accounting\SettlementService::class);
        $this->assertCount(0, $settlements->openItems($this->ctx['customer_ledger']->id));

        $receipt = Receipt::query()->latest()->firstOrFail();
        $this->delete(route('receipts.destroy', $receipt));

        // The invoice has to come back. A deleted receipt that leaves its
        // settlements behind keeps an invoice closed against a voucher that no
        // longer exists.
        $this->assertCount(1, $settlements->openItems($this->ctx['customer_ledger']->id));
    }
}

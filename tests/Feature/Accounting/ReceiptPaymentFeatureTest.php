<?php

namespace Tests\Feature\Accounting;

use App\Models\Payment\Payment;
use App\Models\Receipt\Receipt;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class ReceiptPaymentFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_overpayment_creates_customer_advance_credit_balance(): void
    {
        $service = app(TransactionService::class);

        $service->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-01',
                'reference_type' => 'invoice',
                'reference_id' => $this->ctx['item']->id,
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'debit' => 100,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['sales-revenue']->id,
                    'debit' => 0,
                    'credit' => 100,
                ],
            ],
        );

        $response = $this->post(route('receipts.store'), [
            'number' => 1,
            'date' => '2026-03-02',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'amount' => 120,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'narration' => 'customer overpayment',
        ]);

        $response->assertRedirect(route('receipts.index'));
        $this->assertDatabaseHas('receipts', ['number' => '1']);

        $receipt = Receipt::query()->latest()->firstOrFail();

        // An on-account receipt still relieves what is open before parking the
        // rest: cash 120, receivable 100, advance 20. Dropping the whole 120
        // into advances would report a 100 receivable and a 120 liability for
        // the same money.
        $lines = $receipt->transaction()->firstOrFail()->lines()->get();
        $this->assertCount(3, $lines);

        $this->assertEquals(120, $lines->firstWhere('account_id', $this->ctx['accounts']['cash-in-hand']->id)->debit);
        $this->assertEquals(100, $lines->firstWhere('account_id', $this->ctx['accounts']['account-receivable']->id)->credit);
        $this->assertEquals(20, $lines->firstWhere('account_id', $this->ctx['accounts']['customer-advances']->id)->credit);

        $ledgerBalance = DB::table('transaction_lines')
            ->where('ledger_id', $this->ctx['customer_ledger']->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->value('balance');

        $this->assertEquals(-20.0, (float) $ledgerBalance);
    }

    /**
     * `payment_mode` is cast to the PaymentMode enum on the model, so the
     * export's `(string) $r->payment_mode` was a fatal error rather than a
     * value — every receipt export returned a 500.
     */
    public function test_the_receipt_export_streams_instead_of_crashing_on_the_payment_mode_enum(): void
    {
        $this->post(route('receipts.store'), [
            'number' => 1,
            'date' => '2026-03-02',
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'amount' => 100,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'payment_mode' => 'on_account',
        ])->assertRedirect(route('receipts.index'));

        $this->get(route('receipts.export', ['sortField' => 'date', 'sortDirection' => 'desc']))
            ->assertOk()
            ->assertDownload();
    }

    public function test_the_payment_export_streams_instead_of_crashing_on_the_payment_mode_enum(): void
    {
        $this->post(route('payments.store'), [
            'number' => 1,
            'date' => '2026-03-02',
            'ledger_id' => $this->ctx['supplier_ledger']->id,
            'amount' => 100,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'payment_mode' => 'on_account',
        ])->assertRedirect(route('payments.index'));

        $this->get(route('payments.export', ['sortField' => 'date', 'sortDirection' => 'desc']))
            ->assertOk()
            ->assertDownload();
    }

    public function test_partial_supplier_payment_reduces_payable_balance_without_closing_it(): void
    {
        $service = app(TransactionService::class);

        $service->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-01',
                'reference_type' => 'supplier-invoice',
                'reference_id' => $this->ctx['item']->id,
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['other-expenses']->id,
                    'debit' => 200,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['account-payable']->id,
                    'ledger_id' => $this->ctx['supplier_ledger']->id,
                    'debit' => 0,
                    'credit' => 200,
                ],
            ],
        );

        $response = $this->post(route('payments.store'), [
            'number' => 10,
            'date' => '2026-03-02',
            'ledger_id' => $this->ctx['supplier_ledger']->id,
            'amount' => 50,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'narration' => 'partial payment',
        ]);

        $response->assertRedirect(route('payments.index'));
        $this->assertDatabaseHas('payments', ['number' => '10']);

        $payment = Payment::query()->latest()->firstOrFail();
        $this->assertEquals(2, $payment->transaction()->firstOrFail()->lines()->count());

        $supplierBalance = DB::table('transaction_lines')
            ->where('ledger_id', $this->ctx['supplier_ledger']->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->value('balance');

        $this->assertEquals(-150.0, (float) $supplierBalance);
    }
}

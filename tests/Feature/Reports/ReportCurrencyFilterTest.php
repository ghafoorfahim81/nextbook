<?php

namespace Tests\Feature\Reports;

use App\Models\Administration\Currency;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The cash/party reports carry a currency filter and report the currency and
 * rate each line was booked at. Amounts stay in home currency — the rate column
 * is what makes a foreign-currency line readable.
 */
class ReportCurrencyFilterTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Currency $usd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();

        $this->usd = Currency::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'exchange_rate' => 70,
            'is_active' => true,
            'is_base_currency' => false,
            'flag' => 'us.png',
        ]);

        // A receipt from the customer in each currency, then a payment to the
        // supplier in dollars.
        $this->postReceipt($this->ctx['currency'], 1, 5000, '2026-03-10');
        $this->postReceipt($this->usd, 70, 100, '2026-03-11');
        $this->postPayment($this->usd, 70, 40, '2026-03-12');
    }

    private function postReceipt(Currency $currency, float $rate, float $amount, string $date): void
    {
        app(TransactionService::class)->post(
            header: [
                'currency_id' => $currency->id,
                'rate' => $rate,
                'date' => $date,
                'reference_type' => 'report-seed',
                'reference_id' => $this->ctx['customer_ledger']->id,
            ],
            lines: [
                ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => $amount, 'credit' => 0],
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'debit' => 0,
                    'credit' => $amount,
                ],
            ],
        );
    }

    private function postPayment(Currency $currency, float $rate, float $amount, string $date): void
    {
        app(TransactionService::class)->post(
            header: [
                'currency_id' => $currency->id,
                'rate' => $rate,
                'date' => $date,
                'reference_type' => 'report-seed',
                'reference_id' => $this->ctx['supplier_ledger']->id,
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['account-payable']->id,
                    'ledger_id' => $this->ctx['supplier_ledger']->id,
                    'debit' => $amount,
                    'credit' => 0,
                ],
                ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => 0, 'credit' => $amount],
            ],
        );
    }

    private function report(string $report, array $overrides = []): array
    {
        $response = $this->get(route('reports.index', [
            'report' => $report,
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'per_page' => 25,
            ...$overrides,
        ]));

        $response->assertOk();

        $result = [];

        $response->assertInertia(function (AssertableInertia $page) use (&$result) {
            $page->component('Reports/Index');
            $result = $page->toArray()['props']['result'];
        });

        return $result;
    }

    public function test_receipt_report_reports_currency_and_rate_and_filters_on_currency(): void
    {
        $rows = $this->report('receipt_report')['rows'];

        $this->assertCount(2, $rows);
        $this->assertSame(['USD', 'AFN'], array_column($rows, 'currency'));
        $this->assertSame([70.0, 1.0], array_map('floatval', array_column($rows, 'rate')));

        $filtered = $this->report('receipt_report', ['currency_id' => $this->usd->id]);

        $this->assertCount(1, $filtered['rows']);
        $this->assertSame('USD', $filtered['rows'][0]['currency']);
        // 100 USD at 70 is 7,000 in home currency.
        $this->assertSame(7000.0, (float) $filtered['rows'][0]['amount_received']);
        $this->assertSame(7000.0, (float) $filtered['summary']['total_amount']);
    }

    public function test_payment_report_reports_currency_and_rate_and_filters_on_currency(): void
    {
        $rows = $this->report('payment_report')['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame('USD', $rows[0]['currency']);
        $this->assertSame(70.0, (float) $rows[0]['rate']);

        $filtered = $this->report('payment_report', ['currency_id' => $this->ctx['currency']->id]);

        $this->assertSame([], $filtered['rows']);
        $this->assertSame(0.0, (float) $filtered['summary']['total_amount']);
    }

    public function test_cash_book_reports_currency_and_rate_and_filters_on_currency(): void
    {
        $accountId = $this->ctx['accounts']['cash-in-hand']->id;

        $result = $this->report('cash_book', ['account_id' => $accountId]);

        $this->assertCount(3, $result['rows']);
        $this->assertSame(['AFN', 'USD', 'USD'], array_column($result['rows'], 'currency'));

        $filtered = $this->report('cash_book', [
            'account_id' => $accountId,
            'currency_id' => $this->usd->id,
        ]);

        $this->assertCount(2, $filtered['rows']);
        // 7,000 in, 2,800 out — the running balance is scoped to the filtered set.
        $this->assertSame(7000.0, (float) $filtered['summary']['total_debit']);
        $this->assertSame(2800.0, (float) $filtered['summary']['total_credit']);
        $this->assertSame(4200.0, (float) $filtered['rows'][1]['running_balance']);
    }

    public function test_day_book_reports_currency_and_rate_and_filters_on_currency(): void
    {
        $window = ['date_from' => '2026-03-01', 'date_to' => '2026-03-31'];

        $result = $this->report('day_book_report', $window);

        // Two lines per transaction across three transactions.
        $this->assertCount(6, $result['rows']);
        $this->assertSame(1.0, (float) $result['rows'][0]['rate']);

        $filtered = $this->report('day_book_report', [
            ...$window,
            'currency_id' => $this->usd->id,
        ]);

        $this->assertCount(4, $filtered['rows']);
        $this->assertSame(['USD'], array_values(array_unique(array_column($filtered['rows'], 'currency'))));
    }

    public function test_supplier_statement_filters_on_currency(): void
    {
        $detail = $this->report('supplier_statement', [
            'supplier_id' => $this->ctx['supplier_ledger']->id,
        ]);

        $this->assertCount(1, $detail['rows']);

        $filtered = $this->report('supplier_statement', [
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'currency_id' => $this->ctx['currency']->id,
        ]);

        $this->assertSame([], $filtered['rows']);
    }

    public function test_customer_statement_summary_lists_every_party_under_a_currency_filter(): void
    {
        $summary = $this->report('customer_statement', [
            'currency_id' => $this->usd->id,
        ]);

        // The party still appears; only its figures narrow to the currency.
        $this->assertCount(1, $summary['rows']);
        $this->assertSame(7000.0, (float) $summary['rows'][0]['credit']);
        $this->assertSame(0.0, (float) $summary['rows'][0]['debit']);
    }
}

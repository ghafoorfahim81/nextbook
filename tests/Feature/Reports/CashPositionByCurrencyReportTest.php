<?php

namespace Tests\Feature\Reports;

use App\Models\Account\Account;
use App\Models\Administration\Currency;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class CashPositionByCurrencyReportTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Currency $usd;

    private Account $usdCash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();

        $this->usd = Currency::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'exchange_rate' => 65,
            'is_active' => true,
            'is_base_currency' => false,
            'flag' => 'us.png',
        ]);

        $this->usdCash = Account::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Cash In Hand USD',
            'local_name' => 'صندوق دالری',
            'number' => '1002',
            'slug' => 'cash-in-hand-usd',
            'account_type_id' => $this->ctx['account_types']['cash-or-bank']->id,
        ]);
    }

    private function postCash(Account $account, Currency $currency, float $rate, float $amount, string $date): void
    {
        app(TransactionService::class)->post(
            header: [
                'currency_id' => $currency->id,
                'rate' => $rate,
                'date' => $date,
                'reference_type' => 'report-seed',
                'reference_id' => $account->id,
            ],
            lines: [
                ['account_id' => $account->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $this->ctx['accounts']['opening-balance-equity']->id, 'debit' => 0, 'credit' => $amount],
            ],
        );
    }

    private function report(array $overrides = []): array
    {
        $response = $this->get(route('reports.index', [
            'report' => 'cash_position_by_currency',
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

        return ['rows' => $result['rows'], 'summary' => $result['summary']];
    }

    public function test_it_returns_one_line_per_currency(): void
    {
        // Two separate USD drawers plus an AFN one: the report must fold the
        // dollar accounts into a single USD line.
        $secondUsdCash = Account::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Bank USD',
            'number' => '1003',
            'slug' => 'bank-usd',
            'account_type_id' => $this->ctx['account_types']['cash-or-bank']->id,
        ]);

        $this->postCash($this->usdCash, $this->usd, 65, 1000, '2026-03-10');
        $this->postCash($secondUsdCash, $this->usd, 65, 200, '2026-03-12');
        $this->postCash($this->ctx['accounts']['cash-in-hand'], $this->ctx['currency'], 1, 5000, '2026-03-11');

        ['rows' => $rows, 'summary' => $summary] = $this->report();

        $this->assertCount(2, $rows);

        // Home currency first.
        $this->assertSame('AFN', $rows[0]['currency']);
        $this->assertSame(5000.0, (float) $rows[0]['amount']);
        $this->assertSame(5000.0, (float) $rows[0]['home_equivalent']);

        // Both dollar accounts on one line, in dollars.
        $this->assertSame('USD', $rows[1]['currency']);
        $this->assertSame('US Dollar', $rows[1]['currency_name']);
        $this->assertSame(1200.0, (float) $rows[1]['amount']);
        $this->assertSame(78000.0, (float) $rows[1]['home_equivalent']);

        $this->assertSame(2, $summary['currency_count']);
        $this->assertSame(83000.0, (float) $summary['total_home_equivalent']);
    }

    public function test_it_reports_the_standing_position_not_just_the_window(): void
    {
        // Money received before date_from is still money in the drawer.
        $this->postCash($this->usdCash, $this->usd, 65, 400, '2026-02-20');
        $this->postCash($this->usdCash, $this->usd, 65, 600, '2026-03-10');

        ['rows' => $rows] = $this->report();

        $this->assertCount(1, $rows);
        $this->assertSame(1000.0, (float) $rows[0]['amount']);
    }

    public function test_it_ignores_transactions_after_the_selected_date(): void
    {
        $this->postCash($this->usdCash, $this->usd, 65, 1000, '2026-03-10');
        $this->postCash($this->usdCash, $this->usd, 65, 700, '2026-04-02');

        ['rows' => $rows] = $this->report();

        $this->assertSame(1000.0, (float) $rows[0]['amount']);
    }

    public function test_money_paid_out_reduces_the_position(): void
    {
        $this->postCash($this->usdCash, $this->usd, 65, 1000, '2026-03-10');

        app(TransactionService::class)->post(
            header: [
                'currency_id' => $this->usd->id,
                'rate' => 65,
                'date' => '2026-03-15',
                'reference_type' => 'report-seed',
                'reference_id' => $this->usdCash->id,
            ],
            lines: [
                ['account_id' => $this->ctx['accounts']['other-expenses']->id, 'debit' => 250, 'credit' => 0],
                ['account_id' => $this->usdCash->id, 'debit' => 0, 'credit' => 250],
            ],
        );

        ['rows' => $rows] = $this->report();

        $this->assertSame(750.0, (float) $rows[0]['amount']);
    }

    public function test_it_only_counts_cash_and_bank_accounts(): void
    {
        // A receivable movement must not show up as cash.
        app(TransactionService::class)->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-10',
                'reference_type' => 'report-seed',
                'reference_id' => $this->ctx['customer_ledger']->id,
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'debit' => 900,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['sales-revenue']->id,
                    'debit' => 0,
                    'credit' => 900,
                ],
            ],
        );

        ['rows' => $rows, 'summary' => $summary] = $this->report();

        $this->assertSame([], $rows);
        $this->assertSame(0, $summary['currency_count']);
    }

    public function test_the_export_keeps_the_native_amounts(): void
    {
        $this->postCash($this->usdCash, $this->usd, 65, 1000, '2026-03-10');

        $response = $this->get(route('reports.export', [
            'report' => 'cash_position_by_currency',
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]));

        $response->assertOk();

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertStringContainsString('USD', $sheet);
        $this->assertStringContainsString('<v>1000</v>', $sheet);
        $this->assertStringContainsString('<v>65000</v>', $sheet);
    }
}

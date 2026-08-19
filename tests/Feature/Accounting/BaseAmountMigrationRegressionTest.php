<?php

namespace Tests\Feature\Accounting;

use App\Models\Administration\Currency;
use App\Services\ReportService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The key regression test for the whole line-level currency task.
 *
 * Reports used to derive every AFN figure as `debit * transactions.rate` at
 * read time. They now read the stored `base_debit` / `base_credit`. Those two
 * must agree to the cent, or the migration silently restated the books.
 */
class BaseAmountMigrationRegressionTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->service = app(TransactionService::class);
    }

    private function currency(string $code, float $rate): Currency
    {
        return Currency::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => $code,
            'code' => $code,
            'symbol' => $code,
            'exchange_rate' => $rate,
            'is_active' => true,
            'is_base_currency' => false,
            'flag' => strtolower($code) . '.png',
        ]);
    }

    /**
     * A spread of vouchers across currencies and rates, including an awkward
     * rate that does not divide evenly.
     */
    private function seedLedger(): void
    {
        $usd = $this->currency('USD', 60);
        $irr = $this->currency('IRR', 0.0000017);

        $entries = [
            [$this->ctx['currency']->id, 1, 1500.00],
            [$this->ctx['currency']->id, 1, 233.33],
            [$usd->id, 60, 100.00],
            [$usd->id, 63.5, 249.99],
            [$usd->id, 71.125, 33.33],
            [$irr->id, 0.0000017, 1000000.00],
        ];

        foreach ($entries as $index => [$currencyId, $rate, $amount]) {
            $this->service->post(
                [
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => '2026-08-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'voucher_number' => 'REG-' . ($index + 1),
                ],
                [
                    [
                        'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                        'ledger_id' => $this->ctx['customer_ledger']->id,
                        'debit' => $amount,
                    ],
                    [
                        'account_id' => $this->ctx['accounts']['sales-revenue']->id,
                        'ledger_id' => $this->ctx['customer_ledger']->id,
                        'credit' => $amount,
                    ],
                ]
            );
        }
    }

    /**
     * The pre-migration formula, run against the same rows.
     *
     * @return array{debit: float, credit: float}
     */
    private function legacyTotals(): array
    {
        $row = DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('t.branch_id', $this->ctx['branch']->id)
            ->where('t.status', 'posted')
            ->whereNull('t.deleted_at')
            ->whereNull('tl.deleted_at')
            ->selectRaw('COALESCE(SUM(tl.debit * t.rate), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(tl.credit * t.rate), 0) as total_credit')
            ->first();

        return [
            'debit' => round((float) $row->total_debit, 2),
            'credit' => round((float) $row->total_credit, 2),
        ];
    }

    public function test_stored_base_amounts_match_the_legacy_derived_totals(): void
    {
        $this->seedLedger();

        $legacy = $this->legacyTotals();

        $stored = DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('t.branch_id', $this->ctx['branch']->id)
            ->where('t.status', 'posted')
            ->whereNull('t.deleted_at')
            ->whereNull('tl.deleted_at')
            ->selectRaw('COALESCE(SUM(tl.base_debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(tl.base_credit), 0) as total_credit')
            ->first();

        $this->assertEqualsWithDelta($legacy['debit'], round((float) $stored->total_debit, 2), 0.01);
        $this->assertEqualsWithDelta($legacy['credit'], round((float) $stored->total_credit, 2), 0.01);
    }

    public function test_trial_balance_total_is_unchanged_and_still_balances(): void
    {
        $this->seedLedger();

        $legacy = $this->legacyTotals();

        // The report filters on an inclusive date range; it has no "all time".
        $report = app(ReportService::class)->getTrialBalance([
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'per_page' => 100,
            'page' => 1,
        ]);

        $totals = $report['summary'] ?? null;

        $this->assertNotEmpty($totals, 'Trial balance did not return a summary: ' . json_encode(array_keys($report)));

        $this->assertEqualsWithDelta($legacy['debit'], round((float) $totals['total_debit'], 2), 0.01);
        $this->assertEqualsWithDelta($legacy['credit'], round((float) $totals['total_credit'], 2), 0.01);

        // And the books still balance in base currency.
        $this->assertEqualsWithDelta(
            (float) $totals['total_debit'],
            (float) $totals['total_credit'],
            0.01
        );
    }

    public function test_verify_base_reports_clean_after_posting(): void
    {
        $this->seedLedger();

        $this->artisan('transactions:verify-base')
            ->assertExitCode(0);
    }

    public function test_verify_base_detects_and_repairs_rounding_drift(): void
    {
        $this->seedLedger();

        // Simulate the drift independent per-line rounding can produce.
        $line = DB::table('transaction_lines')
            ->where('base_debit', '>', 0)
            ->orderByDesc('base_debit')
            ->first();

        DB::table('transaction_lines')
            ->where('id', $line->id)
            ->update(['base_debit' => (float) $line->base_debit + 0.01]);

        $this->artisan('transactions:verify-base')->assertExitCode(1);

        $this->artisan('transactions:verify-base --fix')->assertExitCode(0);

        $this->artisan('transactions:verify-base')->assertExitCode(0);

        // The repair must never touch the rate — it is a historical fact.
        $repaired = DB::table('transaction_lines')->where('id', $line->id)->first();
        $this->assertEquals($line->rate, $repaired->rate);
    }

    public function test_account_exposes_balances_split_by_currency(): void
    {
        $this->seedLedger();

        $balances = $this->ctx['accounts']['cash-in-hand']->balances_by_currency;

        $codes = array_column($balances, 'currency_code');

        $this->assertContains('AFN', $codes);
        $this->assertContains('USD', $codes);
        $this->assertContains('IRR', $codes);

        // Base currency sorts first.
        $this->assertSame('AFN', $codes[0]);

        $usd = collect($balances)->firstWhere('currency_code', 'USD');

        // Document-currency total is the sum of the USD amounts themselves,
        // not their AFN value.
        $this->assertEqualsWithDelta(100.00 + 249.99 + 33.33, $usd['net_balance'], 0.01);
        $this->assertEqualsWithDelta(
            (100.00 * 60) + (249.99 * 63.5) + (33.33 * 71.125),
            $usd['base_net_balance'],
            0.05
        );
    }
}

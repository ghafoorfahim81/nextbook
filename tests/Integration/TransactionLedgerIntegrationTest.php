<?php

namespace Tests\Integration;

use App\Models\Ledger\Ledger;
use App\Services\DashboardService;
use App\Services\ReportService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class TransactionLedgerIntegrationTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_trial_balance_handles_zero_transactions(): void
    {
        $service = app(ReportService::class);

        $result = $service->getTrialBalance([
            'report' => 'trial_balance',
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'ledger_id' => null,
            'customer_id' => null,
            'supplier_id' => null,
            'item_id' => null,
            'account_id' => null,
            'per_page' => 25,
            'page' => 1,
        ]);

        $this->assertEquals(0.0, $result['summary']['total_debit']);
        $this->assertEquals(0.0, $result['summary']['total_credit']);
        $this->assertEquals(0.0, $result['summary']['balance']);
    }

    public function test_trial_balance_uses_exchange_rate_for_multi_currency_transactions(): void
    {
        $transactionService = app(TransactionService::class);
        $reportService = app(ReportService::class);

        $transactionService->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-10',
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'debit' => 100,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['account-payable']->id,
                    'ledger_id' => $this->ctx['supplier_ledger']->id,
                    'debit' => 0,
                    'credit' => 100,
                ],
            ],
        );

        $transactionService->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 2,
                'date' => '2026-03-11',
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'debit' => 100,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['account-payable']->id,
                    'ledger_id' => $this->ctx['supplier_ledger']->id,
                    'debit' => 0,
                    'credit' => 100,
                ],
            ],
        );

        $result = $reportService->getTrialBalance([
            'report' => 'trial_balance',
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'ledger_id' => null,
            'customer_id' => null,
            'supplier_id' => null,
            'item_id' => null,
            'account_id' => null,
            'per_page' => 25,
            'page' => 1,
        ]);

        $this->assertEquals(300.0, $result['summary']['total_debit']);
        $this->assertEquals(300.0, $result['summary']['total_credit']);
    }

    public function test_customer_and_supplier_statements_reflect_partial_and_overpayments(): void
    {
        $transactionService = app(TransactionService::class);
        $reportService = app(ReportService::class);

        $transactionService->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-01',
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'debit' => 100,
                    'credit' => 0,
                ],
                ['account_id' => $this->ctx['accounts']['sales-revenue']->id, 'debit' => 0, 'credit' => 100],
            ],
        );

        $transactionService->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-02',
            ],
            lines: [
                ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => 120, 'credit' => 0],
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'debit' => 0,
                    'credit' => 120,
                ],
            ],
        );

        $transactionService->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-03',
            ],
            lines: [
                ['account_id' => $this->ctx['accounts']['other-expenses']->id, 'debit' => 200, 'credit' => 0],
                [
                    'account_id' => $this->ctx['accounts']['account-payable']->id,
                    'ledger_id' => $this->ctx['supplier_ledger']->id,
                    'debit' => 0,
                    'credit' => 200,
                ],
            ],
        );

        $transactionService->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-04',
            ],
            lines: [
                ['account_id' => $this->ctx['accounts']['account-payable']->id, 'ledger_id' => $this->ctx['supplier_ledger']->id, 'debit' => 50, 'credit' => 0],
                ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => 0, 'credit' => 50],
            ],
        );

        $customerStatement = $reportService->getCustomerStatement([
            'report' => 'customer_statement',
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'ledger_id' => null,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'supplier_id' => null,
            'item_id' => null,
            'account_id' => null,
            'per_page' => 25,
            'page' => 1,
        ]);

        $supplierStatement = $reportService->getSupplierStatement([
            'report' => 'supplier_statement',
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'ledger_id' => null,
            'customer_id' => null,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'item_id' => null,
            'account_id' => null,
            'per_page' => 25,
            'page' => 1,
        ]);

        $this->assertEquals(-20.0, $customerStatement['summary']['balance']);
        $this->assertEquals('20.00 Cr', $customerStatement['summary']['balance_label']);
        $this->assertEquals(-150.0, $supplierStatement['summary']['balance']);
    }

    public function test_dashboard_payables_follow_account_payable_balance_and_recover_legacy_supplier_openings(): void
    {
        $transactionService = app(TransactionService::class);
        $dashboardService = app(DashboardService::class);

        $transactionService->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-01',
                'reference_type' => Ledger::class,
                'reference_id' => $this->ctx['supplier_ledger']->id,
                'remark' => 'Legacy opening balance for supplier',
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['opening-balance-equity']->id,
                    'ledger_id' => $this->ctx['supplier_ledger']->id,
                    'debit' => 4500,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['account-payable']->id,
                    'debit' => 0,
                    'credit' => 4500,
                ],
            ],
        );

        $dashboard = $dashboardService->getDashboardData($this->ctx['user']);

        $this->assertEquals(4500.0, $dashboard['kpis']['accounts_payable']);
        $this->assertCount(1, $dashboard['top_lists']['payable_balances']);
        $this->assertSame($this->ctx['supplier_ledger']->id, $dashboard['top_lists']['payable_balances'][0]['id']);
        $this->assertEquals(4500.0, $dashboard['top_lists']['payable_balances'][0]['balance']);
    }
}

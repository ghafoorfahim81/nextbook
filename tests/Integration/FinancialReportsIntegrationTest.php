<?php

namespace Tests\Integration;

use App\Services\ReportService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class FinancialReportsIntegrationTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_balance_sheet_and_income_statement_summaries_are_mathematically_correct(): void
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
                ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $this->ctx['accounts']['retained-earnings']->id, 'debit' => 0, 'credit' => 1000],
            ],
        );

        $transactionService->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-05',
            ],
            lines: [
                ['account_id' => $this->ctx['accounts']['account-receivable']->id, 'ledger_id' => $this->ctx['customer_ledger']->id, 'debit' => 500, 'credit' => 0],
                ['account_id' => $this->ctx['accounts']['sales-revenue']->id, 'debit' => 0, 'credit' => 500],
            ],
        );

        $transactionService->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-06',
            ],
            lines: [
                ['account_id' => $this->ctx['accounts']['cost-of-goods-sold']->id, 'debit' => 200, 'credit' => 0],
                ['account_id' => $this->ctx['accounts']['inventory-stock']->id, 'debit' => 0, 'credit' => 200],
            ],
        );

        $transactionService->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-07',
            ],
            lines: [
                ['account_id' => $this->ctx['accounts']['other-expenses']->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => 0, 'credit' => 100],
            ],
        );

        $filters = [
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
        ];

        $incomeStatement = $reportService->getIncomeStatement(array_merge($filters, ['report' => 'income_statement']));
        $balanceSheet = $reportService->getBalanceSheet(array_merge($filters, ['report' => 'balance_sheet']));

        $this->assertEquals(500.0, $incomeStatement['summary']['total_revenue']);
        $this->assertEquals(200.0, $incomeStatement['summary']['total_cost_of_goods_sold']);
        $this->assertEquals(100.0, $incomeStatement['summary']['total_expenses']);
        $this->assertEquals(200.0, $incomeStatement['summary']['net_profit']);

        $this->assertEquals($balanceSheet['summary']['total_assets'], $balanceSheet['summary']['equation_total']);
        $this->assertEquals(1200.0, $balanceSheet['summary']['total_assets']);
        $this->assertEquals(1200.0, $balanceSheet['summary']['equation_total']);
    }
}

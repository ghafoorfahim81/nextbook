<?php

namespace Tests\Feature\Reports;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Services\StockService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class ReportsFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_trial_balance_report_endpoint_returns_calculated_summary(): void
    {
        $transactionService = app(TransactionService::class);

        $transactionService->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-10',
                'reference_type' => 'report-seed',
                'reference_id' => $this->ctx['item']->id,
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'debit' => 300,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['account-payable']->id,
                    'ledger_id' => $this->ctx['supplier_ledger']->id,
                    'debit' => 0,
                    'credit' => 300,
                ],
            ],
        );

        $response = $this->get(route('reports.index', [
            'report' => 'trial_balance',
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'per_page' => 25,
        ]));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) {
            $page->component('Reports/Index')
                ->where('result.summary.total_debit', 300)
                ->where('result.summary.total_credit', 300);
        });
    }

    public function test_reports_export_streams_inventory_valuation_xlsx(): void
    {
        $stockService = app(StockService::class);

        $stockService->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 8,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => 25,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'date' => '2026-03-12',
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'report-stock-seed',
            'reference_id' => $this->ctx['item']->id,
        ]);

        $response = $this->get(route('reports.export', [
            'report' => 'inventory_valuation',
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $xlsx = $response->streamedContent();
        $tmpPath = tempnam(sys_get_temp_dir(), 'report_xlsx_');
        $this->assertNotFalse($tmpPath);

        file_put_contents($tmpPath, $xlsx);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmpPath));

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($sheetXml);
        $this->assertTrue(str_contains($sheetXml, 'Test Item'));
        $this->assertTrue(str_contains($sheetXml, 'Average cost'));

        $zip->close();
        @unlink($tmpPath);
    }

    public function test_group_summary_report_falls_back_to_slug_when_account_type_nature_is_missing(): void
    {
        DB::table('account_types')
            ->where('branch_id', $this->ctx['branch']->id)
            ->update(['nature' => null]);

        $response = $this->get(route('reports.index', [
            'report' => 'group_summary_report',
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'per_page' => 25,
        ]));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) {
            $page->component('Reports/Index')
                ->has('result.rows')
                ->has('result.meta.sections')
                ->where('result.meta.sections.0.rows.0.account_name', 'Accounts Receivable')
                ->where('result.meta.layout', 'group_summary');
        });
    }

    public function test_journal_book_report_falls_back_to_slug_when_account_type_nature_is_missing(): void
    {
        DB::table('account_types')
            ->where('branch_id', $this->ctx['branch']->id)
            ->update(['nature' => null]);

        $response = $this->get(route('reports.index', [
            'report' => 'journal_book_report',
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'per_page' => 25,
        ]));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) {
            $page->component('Reports/Index')
                ->has('result.rows')
                ->where('result.rows.0.account_type', 'Accounts Receivable')
                ->where('result.rows.0.total_debit', 0);
        });
    }
}

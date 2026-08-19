<?php

namespace Tests\Feature\Ledger;

use App\Models\Administration\Currency;
use App\Models\Ledger\Ledger;
use App\Services\PdfExportService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class LedgerStatementPdfExportTest extends TestCase
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
    }

    private function postToLedger(Ledger $ledger, Currency $currency, float $rate, float $amount, string $date): void
    {
        $ledgerAccount = $ledger->type->value === 'supplier'
            ? $this->ctx['accounts']['account-payable']->id
            : $this->ctx['accounts']['account-receivable']->id;

        app(TransactionService::class)->post(
            header: [
                'currency_id' => $currency->id,
                'rate' => $rate,
                'date' => $date,
                'reference_type' => 'statement-seed',
                'reference_id' => $ledger->id,
            ],
            lines: [
                [
                    'account_id' => $ledgerAccount,
                    'ledger_id' => $ledger->id,
                    'debit' => $amount > 0 ? $amount : 0,
                    'credit' => $amount < 0 ? abs($amount) : 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['opening-balance-equity']->id,
                    'debit' => $amount < 0 ? abs($amount) : 0,
                    'credit' => $amount > 0 ? $amount : 0,
                ],
            ],
        );
    }

    public function test_customer_statement_exports_as_a_pdf_document(): void
    {
        $customer = $this->ctx['customer_ledger'];
        $this->postToLedger($customer, $this->usd, 70, 100, '2026-03-01');

        $response = $this->get(route('customers.export', [
            'customer' => $customer->id,
            'list' => 'statement',
            'format' => 'pdf',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('.pdf', $response->headers->get('content-disposition'));
    }

    public function test_supplier_statement_exports_as_a_pdf_document(): void
    {
        $supplier = $this->ctx['supplier_ledger'];
        $this->postToLedger($supplier, $this->usd, 70, -500, '2026-03-01');

        $response = $this->get(route('suppliers.export', [
            'supplier' => $supplier->id,
            'list' => 'statement',
            'format' => 'pdf',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_export_still_defaults_to_a_spreadsheet_when_no_format_is_given(): void
    {
        $customer = $this->ctx['customer_ledger'];
        $this->postToLedger($customer, $this->usd, 70, 100, '2026-03-01');

        $this->get(route('customers.export', [
            'customer' => $customer->id,
            'list' => 'statement',
        ]))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
    }

    public function test_an_unknown_format_is_rejected(): void
    {
        $customer = $this->ctx['customer_ledger'];

        $this->get(route('customers.export', [
            'customer' => $customer->id,
            'list' => 'statement',
            'format' => 'docx',
        ]))->assertSessionHasErrors('format');
    }

    /**
     * The Persian glyphs are what a DejaVu fallback would silently mangle, so the
     * embedded IranYekan subset is the thing worth asserting on.
     */
    public function test_persian_content_embeds_the_iranyekan_subset(): void
    {
        app()->setLocale('fa');

        $pdf = app(PdfExportService::class)->raw([
            'filename' => 'statement.xlsx',
            'sheet_title' => 'صورتحساب مشتری',
            'company_name' => 'شرکت نمونه',
            'rtl' => true,
            'columns' => [
                ['key' => 'description', 'label' => 'تفصیل', 'width' => 30],
                ['key' => 'debit', 'label' => 'بدهکار', 'type' => 'money', 'align' => 'right', 'width' => 14],
            ],
            'rows' => [
                ['description' => 'محمد حسین علی زاده', 'debit' => 1300665.42],
            ],
        ]);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('IRANYekan', $pdf);
    }
}

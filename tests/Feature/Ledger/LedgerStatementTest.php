<?php

namespace Tests\Feature\Ledger;

use App\Models\Administration\Currency;
use App\Models\Ledger\Ledger;
use App\Services\LedgerStatementService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class LedgerStatementTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Currency $usd;

    private Currency $eur;

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

        $this->eur = Currency::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Euro',
            'code' => 'EUR',
            'symbol' => '€',
            'exchange_rate' => 80,
            'is_active' => true,
            'is_base_currency' => false,
            'flag' => 'eu.png',
        ]);
    }

    /**
     * Post a single balanced entry that moves the given ledger.
     *
     * @param  float  $amount  positive debits the ledger, negative credits it
     */
    private function postToLedger(
        Ledger $ledger,
        Currency $currency,
        float $rate,
        float $amount,
        string $date,
        string $counterAccountSlug = 'opening-balance-equity',
    ): void {
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
                    'account_id' => $this->ctx['accounts'][$counterAccountSlug]->id,
                    'debit' => $amount < 0 ? abs($amount) : 0,
                    'credit' => $amount > 0 ? $amount : 0,
                ],
            ],
        );
    }

    private function seedMultiCurrencyCustomer(): Ledger
    {
        $customer = $this->ctx['customer_ledger'];

        // The scenario from the field: one customer owing three currencies at once.
        $this->postToLedger($customer, $this->usd, 70, 100, '2026-03-01');
        $this->postToLedger($customer, $this->ctx['currency'], 1, 10000, '2026-03-02');
        $this->postToLedger($customer, $this->eur, 80, 1200, '2026-03-03');

        return $customer->refresh();
    }

    public function test_statement_reports_one_section_per_currency_without_blending(): void
    {
        $customer = $this->seedMultiCurrencyCustomer();

        $statement = app(LedgerStatementService::class)->build($customer, ['date_to' => '2026-12-31']);

        $sections = collect($statement['sections'])->keyBy('currency_code');

        $this->assertCount(3, $sections, 'Each currency must get its own section.');

        $this->assertSame(100.0, $sections['USD']['closing_balance']);
        $this->assertSame(10000.0, $sections['AFN']['closing_balance']);
        $this->assertSame(1200.0, $sections['EUR']['closing_balance']);

        // Each currency keeps its own face value; nothing is summed across them.
        $this->assertSame('100.00 Dr', $sections['USD']['closing_label']);
        $this->assertSame('1200.00 Dr', $sections['EUR']['closing_label']);
    }

    public function test_home_equivalent_uses_each_transactions_own_rate(): void
    {
        $customer = $this->seedMultiCurrencyCustomer();

        $statement = app(LedgerStatementService::class)->build($customer, ['date_to' => '2026-12-31']);

        // 100 * 70 + 10000 * 1 + 1200 * 80 = 113000
        $this->assertSame(113000.0, $statement['totals']['home_equivalent']);
        $this->assertSame('dr', $statement['totals']['home_equivalent_nature']);
    }

    public function test_running_balance_accumulates_within_a_currency_only(): void
    {
        $customer = $this->seedMultiCurrencyCustomer();

        // A 40 USD receipt must reduce the USD balance and leave AFN/EUR untouched.
        $this->postToLedger($customer, $this->usd, 70, -40, '2026-03-10', 'cash-in-hand');

        $statement = app(LedgerStatementService::class)->build($customer, ['date_to' => '2026-12-31']);
        $sections = collect($statement['sections'])->keyBy('currency_code');

        $this->assertSame(60.0, $sections['USD']['closing_balance']);
        $this->assertSame(10000.0, $sections['AFN']['closing_balance']);
        $this->assertSame(1200.0, $sections['EUR']['closing_balance']);

        $usdRows = $sections['USD']['rows'];
        $this->assertCount(2, $usdRows);
        $this->assertSame(100.0, $usdRows[0]['balance']);
        $this->assertSame(60.0, $usdRows[1]['balance']);
    }

    public function test_brought_forward_balance_opens_a_narrowed_window(): void
    {
        $customer = $this->seedMultiCurrencyCustomer();
        $this->postToLedger($customer, $this->usd, 70, 25, '2026-04-15');

        $statement = app(LedgerStatementService::class)->build($customer, [
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]);

        $sections = collect($statement['sections'])->keyBy('currency_code');

        // March activity sits in the opening balance, not in the rows.
        $this->assertSame(100.0, $sections['USD']['opening_balance']);
        $this->assertCount(1, $sections['USD']['rows']);
        $this->assertSame(125.0, $sections['USD']['closing_balance']);

        // Currencies with no April movement still appear, carrying their balance.
        $this->assertSame(10000.0, $sections['AFN']['opening_balance']);
        $this->assertSame(10000.0, $sections['AFN']['closing_balance']);
        $this->assertCount(0, $sections['AFN']['rows']);
    }

    public function test_currency_filter_narrows_to_a_single_section(): void
    {
        $customer = $this->seedMultiCurrencyCustomer();

        $statement = app(LedgerStatementService::class)->build($customer, [
            'date_to' => '2026-12-31',
            'currency_id' => $this->eur->id,
        ]);

        $this->assertCount(1, $statement['sections']);
        $this->assertSame('EUR', $statement['sections'][0]['currency_code']);

        // The dropdown still offers every currency the ledger has ever used.
        $this->assertCount(3, $statement['currencies']);
    }

    public function test_supplier_statement_is_credit_normal(): void
    {
        $supplier = $this->ctx['supplier_ledger'];

        // We owe the supplier 500 USD.
        $this->postToLedger($supplier, $this->usd, 70, -500, '2026-03-01');

        $statement = app(LedgerStatementService::class)->build($supplier, ['date_to' => '2026-12-31']);
        $section = $statement['sections'][0];

        $this->assertSame(-500.0, $section['closing_balance']);
        $this->assertSame('cr', $section['closing_nature']);
        $this->assertSame('500.00 Cr', $section['closing_label']);
        $this->assertTrue($section['is_normal_balance'], 'A payable is the supplier\'s normal balance.');

        // Aging buckets a payable by its own document age, not by sign.
        $this->assertSame(500.0, $section['aging']['total']);
    }

    public function test_aging_applies_settlements_oldest_first(): void
    {
        $customer = $this->ctx['customer_ledger'];

        $this->postToLedger($customer, $this->usd, 70, 300, '2026-01-01');
        $this->postToLedger($customer, $this->usd, 70, 200, '2026-06-01');
        // A 300 payment must clear the January invoice entirely.
        $this->postToLedger($customer, $this->usd, 70, -300, '2026-06-15', 'cash-in-hand');

        $statement = app(LedgerStatementService::class)->build($customer, ['date_to' => '2026-06-20']);
        $aging = $statement['sections'][0]['aging'];

        $this->assertSame(200.0, $aging['total']);
        // Only the June invoice survives, 19 days old on 2026-06-20.
        $this->assertSame(200.0, $aging['days_30']);
        $this->assertSame(0.0, $aging['days_90_plus']);
    }

    public function test_only_posted_non_deleted_lines_are_included(): void
    {
        $customer = $this->seedMultiCurrencyCustomer();

        \App\Models\Transaction\Transaction::query()
            ->where('currency_id', $this->eur->id)
            ->update(['status' => \App\Enums\TransactionStatus::CANCELLED->value]);

        $statement = app(LedgerStatementService::class)->build($customer, ['date_to' => '2026-12-31']);
        $codes = collect($statement['sections'])->pluck('currency_code');

        $this->assertFalse($codes->contains('EUR'), 'Cancelled transactions must not reach the statement.');
        $this->assertCount(2, $statement['sections']);
    }

    public function test_customer_show_page_receives_the_statement_prop(): void
    {
        $customer = $this->seedMultiCurrencyCustomer();

        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Ledgers/Customers/Show')
                ->has('ledgerStatement.sections', 3)
                ->where('ledgerStatement.meta.ledger_type', 'customer')
            );
    }

    public function test_supplier_show_page_receives_the_statement_prop(): void
    {
        $supplier = $this->ctx['supplier_ledger'];
        $this->postToLedger($supplier, $this->usd, 70, -500, '2026-03-01');

        $this->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Ledgers/Suppliers/Show')
                ->has('ledgerStatement.sections', 1)
                ->where('ledgerStatement.meta.ledger_type', 'supplier')
            );
    }
}

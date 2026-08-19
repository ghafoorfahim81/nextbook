<?php

namespace Tests\Feature\Ledger;

use App\Models\Administration\Currency;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * A party can hold an opening balance in several currencies at once. Each one is
 * posted as its own transaction, because `transactions.currency_id` carries a
 * single currency for the whole voucher.
 */
class LedgerOpeningTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Currency $afn;

    private Currency $usd;

    private Currency $eur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();

        $this->afn = $this->ctx['currency'];

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
            'exchange_rate' => 78,
            'is_active' => true,
            'is_base_currency' => false,
            'flag' => 'eu.png',
        ]);
    }

    /** The scenario that motivated this: 100 USD + 10,000 AFN + 1,200 EUR. */
    public function test_customer_opening_posts_one_transaction_per_currency(): void
    {
        $response = $this->post(route('customers.store'), [
            'name' => 'Multi Currency Customer',
            'code' => 'C-001',
            'currency_id' => $this->afn->id,
            'openings' => [
                ['currency_id' => $this->afn->id, 'rate' => 1, 'amount' => 10000, 'remark' => 'afn part'],
                ['currency_id' => $this->usd->id, 'rate' => 70, 'amount' => 100, 'remark' => 'usd part'],
                ['currency_id' => $this->eur->id, 'rate' => 78, 'amount' => 1200, 'remark' => 'eur part'],
            ],
        ]);

        $response->assertRedirect();

        $customer = Ledger::where('name', 'Multi Currency Customer')->firstOrFail();

        $this->assertCount(3, $customer->openings);

        $byCurrency = $customer->openings
            ->load('transaction.lines')
            ->keyBy(fn ($opening) => $opening->transaction->currency_id);

        $arId = $this->ctx['accounts']['account-receivable']->id;

        foreach ([
            [$this->afn->id, 1.0, 10000.0],
            [$this->usd->id, 70.0, 100.0],
            [$this->eur->id, 78.0, 1200.0],
        ] as [$currencyId, $rate, $amount]) {
            $opening = $byCurrency[$currencyId];

            $this->assertEquals($rate, (float) $opening->transaction->rate);

            // The customer side is a debit: they owe us.
            $arLine = $opening->transaction->lines->firstWhere('account_id', $arId);
            $this->assertNotNull($arLine);
            $this->assertEquals($amount, (float) $arLine->debit);
            $this->assertEquals($customer->id, $arLine->ledger_id);

            // Each voucher balances in its own currency.
            $this->assertEquals(
                (float) $opening->transaction->lines->sum('debit'),
                (float) $opening->transaction->lines->sum('credit'),
            );
        }

        // 10,000 + (100 × 70) + (1,200 × 78) = 110,600 AFN.
        $this->assertEquals(110600.0, (float) $customer->statement['balance_amount']);
        $this->assertSame('dr', $customer->statement['balance_nature']);
    }

    public function test_supplier_opening_credits_payables_per_currency(): void
    {
        $this->post(route('suppliers.store'), [
            'name' => 'Multi Currency Supplier',
            'code' => 'S-001',
            'currency_id' => $this->afn->id,
            'openings' => [
                ['currency_id' => $this->usd->id, 'rate' => 70, 'amount' => 500],
                ['currency_id' => $this->eur->id, 'rate' => 78, 'amount' => 200],
            ],
        ])->assertRedirect();

        $supplier = Ledger::where('name', 'Multi Currency Supplier')->firstOrFail();
        $apId = $this->ctx['accounts']['account-payable']->id;

        $this->assertCount(2, $supplier->openings);

        foreach ($supplier->openings->load('transaction.lines') as $opening) {
            $apLine = $opening->transaction->lines->firstWhere('account_id', $apId);
            $this->assertNotNull($apLine);
            // The supplier side is a credit: we owe them.
            $this->assertGreaterThan(0, (float) $apLine->credit);
            $this->assertEquals(0.0, (float) $apLine->debit);
        }

        // (500 × 70) + (200 × 78) = 50,600 AFN owed.
        $this->assertEquals(50600.0, (float) $supplier->statement['balance_amount']);
        $this->assertSame('cr', $supplier->statement['balance_nature']);
    }

    /** Blank rows are what the form sends for every untouched currency. */
    public function test_blank_rows_are_ignored(): void
    {
        $this->post(route('customers.store'), [
            'name' => 'Partly Opened',
            'code' => 'C-002',
            'currency_id' => $this->afn->id,
            'openings' => [
                ['currency_id' => $this->afn->id, 'rate' => 1, 'amount' => ''],
                ['currency_id' => $this->usd->id, 'rate' => 70, 'amount' => 100],
                ['currency_id' => $this->eur->id, 'rate' => 78, 'amount' => 0],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $customer = Ledger::where('name', 'Partly Opened')->firstOrFail();

        $this->assertCount(1, $customer->openings);
        $this->assertEquals($this->usd->id, $customer->openings->first()->transaction->currency_id);
    }

    public function test_the_same_currency_cannot_be_opened_twice(): void
    {
        $this->post(route('customers.store'), [
            'name' => 'Duplicate Currency',
            'code' => 'C-003',
            'currency_id' => $this->afn->id,
            'openings' => [
                ['currency_id' => $this->usd->id, 'rate' => 70, 'amount' => 100],
                ['currency_id' => $this->usd->id, 'rate' => 70, 'amount' => 250],
            ],
        ])->assertSessionHasErrors('openings.1.currency_id');

        $this->assertDatabaseMissing('ledgers', ['name' => 'Duplicate Currency']);
    }

    /**
     * A zero rate would value the whole opening at nothing, since every report
     * reads amounts as `line * transactions.rate`.
     */
    public function test_a_filled_row_requires_a_positive_rate(): void
    {
        $this->post(route('customers.store'), [
            'name' => 'Zero Rate',
            'code' => 'C-004',
            'currency_id' => $this->afn->id,
            'openings' => [
                ['currency_id' => $this->afn->id, 'rate' => 1, 'amount' => 0],
                ['currency_id' => $this->usd->id, 'rate' => 0, 'amount' => 100],
            ],
        ])->assertSessionHasErrors('openings.1.rate');
    }

    public function test_update_replaces_the_whole_opening_set(): void
    {
        $this->post(route('customers.store'), [
            'name' => 'Rebuilt',
            'code' => 'C-005',
            'currency_id' => $this->afn->id,
            'openings' => [
                ['currency_id' => $this->usd->id, 'rate' => 70, 'amount' => 100],
                ['currency_id' => $this->eur->id, 'rate' => 78, 'amount' => 1200],
            ],
        ])->assertRedirect();

        $customer = Ledger::where('name', 'Rebuilt')->firstOrFail();
        $originalTransactionIds = $customer->openings->pluck('transaction_id')->all();

        $this->patch(route('customers.update', $customer->id), [
            'name' => 'Rebuilt',
            'code' => 'C-005',
            'currency_id' => $this->afn->id,
            'openings' => [
                // EUR dropped, USD revised, AFN added.
                ['currency_id' => $this->afn->id, 'rate' => 1, 'amount' => 5000],
                ['currency_id' => $this->usd->id, 'rate' => 70, 'amount' => 250],
                ['currency_id' => $this->eur->id, 'rate' => 78, 'amount' => ''],
            ],
        ])->assertRedirect();

        $customer->refresh()->load('openings.transaction');

        $this->assertCount(2, $customer->openings);
        $this->assertEqualsCanonicalizing(
            [$this->afn->id, $this->usd->id],
            $customer->openings->pluck('transaction.currency_id')->all(),
        );

        // The superseded vouchers are gone rather than left trashed, so repeated
        // edits do not accumulate deleted records.
        foreach ($originalTransactionIds as $transactionId) {
            $this->assertDatabaseMissing('transactions', ['id' => $transactionId]);
            $this->assertSame(
                0,
                TransactionLine::withTrashed()->where('transaction_id', $transactionId)->count(),
            );
        }

        // 5,000 + (250 × 70) = 22,500 AFN.
        $this->assertEquals(22500.0, (float) $customer->statement['balance_amount']);
    }

    public function test_a_customer_with_only_openings_can_still_be_deleted(): void
    {
        $this->post(route('customers.store'), [
            'name' => 'Deletable',
            'code' => 'C-006',
            'currency_id' => $this->afn->id,
            'openings' => [
                ['currency_id' => $this->usd->id, 'rate' => 70, 'amount' => 100],
                ['currency_id' => $this->eur->id, 'rate' => 78, 'amount' => 1200],
            ],
        ])->assertRedirect();

        $customer = Ledger::where('name', 'Deletable')->firstOrFail();
        $transactionIds = $customer->openings->pluck('transaction_id')->all();

        $this->delete(route('customers.destroy', $customer->id))->assertRedirect();

        $this->assertSoftDeleted('ledgers', ['id' => $customer->id]);

        foreach ($transactionIds as $transactionId) {
            $this->assertSoftDeleted('transactions', ['id' => $transactionId]);
        }

        $this->assertSame(0, Transaction::whereIn('id', $transactionIds)->count());
    }
}

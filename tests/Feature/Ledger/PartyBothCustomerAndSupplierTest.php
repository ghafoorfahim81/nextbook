<?php

namespace Tests\Feature\Ledger;

use App\Enums\LedgerType;
use App\Models\Ledger\Ledger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * What the system allows today for a party who both buys and sells.
 *
 * Establishes the ground truth before any design decision: whether one ledger
 * can carry two roles, whether two ledgers can share a name, and what the
 * party's position looks like when both sides carry a balance.
 */
class PartyBothCustomerAndSupplierTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
    }

    private function makeLedger(string $name, LedgerType $type, array $extra = []): Ledger
    {
        return Ledger::factory()->create(array_merge([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'name' => $name,
            'type' => $type->value,
            'is_active' => true,
        ], $extra));
    }

    public function test_a_ledger_carries_exactly_one_role(): void
    {
        $ledger = $this->makeLedger('Ahmad Trading', LedgerType::CUSTOMER);

        // The column is a single enum. There is no second role to set.
        $this->assertSame(LedgerType::CUSTOMER->value, $ledger->type->value ?? $ledger->type);

        $this->assertFalse(
            DB::getSchemaBuilder()->hasColumn('ledgers', 'types'),
            'If a ledger ever gains multiple roles, this test should be revisited.',
        );
    }

    public function test_whether_two_ledgers_may_share_one_name(): void
    {
        $this->makeLedger('Ahmad Trading', LedgerType::CUSTOMER, ['code' => 'C-1']);

        $sameName = true;

        try {
            $this->makeLedger('Ahmad Trading', LedgerType::SUPPLIER, ['code' => 'S-1']);
        } catch (QueryException $e) {
            $sameName = false;
        }

        // Recorded rather than asserted either way: this is the constraint the
        // design conversation turns on, and it is a property of the schema.
        $this->assertTrue(
            $sameName || ! $sameName,
            'placeholder',
        );

        fwrite(STDERR, "\n[PARTY] two ledgers with the same name: " . ($sameName ? 'ALLOWED' : 'REFUSED') . "\n");

        if ($sameName) {
            $this->assertSame(
                2,
                Ledger::query()->where('name', 'Ahmad Trading')->count(),
                'Both rows should be there when the name is not enforced unique.',
            );
        }
    }

    public function test_the_two_sides_are_reported_separately(): void
    {
        $customer = $this->makeLedger('Ahmad as buyer', LedgerType::CUSTOMER, ['code' => 'C-2']);
        $supplier = $this->makeLedger('Ahmad as seller', LedgerType::SUPPLIER, ['code' => 'S-2']);

        // A receivable on one side and a payable on the other.
        $this->postLine($customer, debit: 5000, accountSlug: 'account-receivable');
        $this->postLine($supplier, credit: 3000, accountSlug: 'account-payable');

        $this->assertEqualsWithDelta(5000.0, $this->balanceOf($customer), 0.01);
        $this->assertEqualsWithDelta(-3000.0, $this->balanceOf($supplier), 0.01);

        // Nothing in the data links the two: they are two unrelated parties as
        // far as every report is concerned.
        $this->assertNotSame($customer->id, $supplier->id);
    }

    /**
     * The forms filter their pickers by type, but the requests only check that
     * the id exists. This asks what actually happens when a customer ledger is
     * used where a supplier is expected — through the API, an import, or a form
     * that forgets to filter.
     */
    public function test_what_happens_when_a_customer_is_used_as_a_supplier(): void
    {
        $customer = $this->makeLedger('Ahmad Trading', LedgerType::CUSTOMER, ['code' => 'C-3']);

        app(\App\Services\StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => \App\Enums\StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 50,
            'source' => \App\Enums\StockSourceType::OPENING->value,
            'unit_cost' => 10,
            'status' => \App\Enums\StockStatus::POSTED->value,
            'batch' => null, 'expire_date' => null,
            'date' => now()->toDateString(),
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => null, 'reference_id' => null,
        ]);

        $response = $this->post(route('purchases.store'), [
            'number' => 8801,
            'supplier_id' => $customer->id,
            'date' => now()->toDateString(),
            'transaction_total' => 1000,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'purchase_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => 100,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 10,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ]);

        $errors = session('errors');
        $accepted = $errors === null || count($errors->all()) === 0;

        fwrite(STDERR, "
[PARTY] purchase booked against a CUSTOMER ledger: "
            . ($accepted ? 'ACCEPTED' : 'REFUSED (' . implode(' | ', $errors->all()) . ')') . "
");

        if (! $accepted) {
            $this->assertTrue(true, 'Refused at the request — the type is enforced.');

            return;
        }

        // It went through. Which control account did the party line land on?
        $accounts = DB::table('transaction_lines as tl')
            ->join('accounts as a', 'a.id', '=', 'tl.account_id')
            ->where('tl.ledger_id', $customer->id)
            ->pluck('a.slug')
            ->unique()
            ->values();

        fwrite(STDERR, '[PARTY] control accounts used: ' . $accounts->implode(', ') . "
");

        // And can the money owed to them ever be found? openItems resolves the
        // control account from the ledger's TYPE, so a customer's payable lines
        // are looked for on the receivable account and never found.
        $payableSide = app(\App\Services\Accounting\SettlementService::class)
            ->openItems($customer->id, null, \App\Services\Accounting\SettlementService::DIRECTION_OUT)
            ->sum('remaining_amount');

        fwrite(STDERR, '[PARTY] what a payment form would offer to settle: ' . $payableSide . "
");

        $this->assertTrue(true, 'Recorded for the design discussion.');
    }

    private function postLine(Ledger $ledger, float $debit = 0, float $credit = 0, string $accountSlug = 'account-receivable'): void
    {
        $transactionId = (string) \Illuminate\Support\Str::ulid();

        DB::table('transactions')->insert([
            'id' => $transactionId,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'date' => now()->toDateString(),
            'status' => 'posted',
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('transaction_lines')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'transaction_id' => $transactionId,
            'account_id' => $this->ctx['accounts'][$accountSlug]->id,
            'ledger_id' => $ledger->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'debit' => $debit,
            'credit' => $credit,
            'base_debit' => $debit,
            'base_credit' => $credit,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function balanceOf(Ledger $ledger): float
    {
        return (float) DB::table('transaction_lines')
            ->where('ledger_id', $ledger->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) AS b')
            ->value('b');
    }
}

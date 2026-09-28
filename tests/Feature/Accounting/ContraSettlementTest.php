<?php

namespace Tests\Feature\Accounting;

use App\Enums\LedgerType;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Ledger\Ledger;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use App\Services\Accounting\ContraSettlementService;
use App\Services\Accounting\SettlementService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Offsetting one party's two accounts against each other.
 *
 * Ahmad buys from us and sells to us. Rather than sending money both ways, the
 * smaller side is set off against the larger and only the difference is paid.
 * The test holds the posting to the one standard that matters: each party's
 * balance must end where the offset says, the invoices on both sides must
 * actually close, and the clearing account the two halves pass through must be
 * left at zero.
 */
class ContraSettlementTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Ledger $customer;

    private Ledger $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);

        $this->customer = $this->ctx['customer_ledger'];
        $this->supplier = $this->ctx['supplier_ledger'];

        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 500,
            'source' => StockSourceType::OPENING->value,
            'unit_cost' => 10,
            'status' => StockStatus::POSTED->value,
            'batch' => null, 'expire_date' => null,
            'date' => now()->toDateString(),
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => null, 'reference_id' => null,
        ]);
    }

    // ------------------------------------------------------------- fixtures

    private function sellOnLoan(float $total): Sale
    {
        $this->post(route('sales.store'), [
            'number' => random_int(1000, 1999),
            'customer_id' => $this->customer->id,
            'date' => now()->toDateString(),
            'transaction_total' => $total,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $total / 20,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 20,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'))->assertSessionHasNoErrors();

        return Sale::query()->orderByDesc('id')->firstOrFail();
    }

    private function buyOnLoan(float $total): Purchase
    {
        $this->post(route('purchases.store'), [
            'number' => random_int(1000, 1999),
            'supplier_id' => $this->supplier->id,
            'date' => now()->toDateString(),
            'transaction_total' => $total,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'purchase_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $total / 10,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 10,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        return Purchase::query()->orderByDesc('id')->firstOrFail();
    }

    // --------------------------------------------------------------- probes

    private function balanceOf(Ledger $ledger): float
    {
        return (float) DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('tl.ledger_id', $ledger->id)
            ->whereIn('t.status', ['posted', 'reversed'])
            ->whereNull('tl.deleted_at')
            ->whereNull('t.deleted_at')
            ->selectRaw('COALESCE(SUM(tl.debit - tl.credit), 0) AS b')
            ->value('b');
    }

    private function accountBalance(string $slug): float
    {
        return (float) DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('tl.account_id', $this->ctx['accounts'][$slug]->id)
            ->whereIn('t.status', ['posted', 'reversed'])
            ->whereNull('tl.deleted_at')
            ->selectRaw('COALESCE(SUM(tl.debit - tl.credit), 0) AS b')
            ->value('b');
    }

    private function openTotal(Ledger $ledger, string $direction): float
    {
        return (float) app(SettlementService::class)
            ->openItems($ledger->id, null, $direction)
            ->sum('remaining_amount');
    }

    private function offset(float $amount): array
    {
        return app(ContraSettlementService::class)->offset(
            customer: $this->customer,
            supplier: $this->supplier,
            voucher: [
                'date' => now()->toDateString(),
                'branch_id' => $this->ctx['branch']->id,
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'amount' => $amount,
                'remark' => 'set-off by mutual agreement',
            ],
        );
    }

    // ---------------------------------------------------------------- tests

    public function test_a_set_off_moves_both_balances_and_leaves_clearing_at_zero(): void
    {
        $this->sellOnLoan(5000);   // they owe us 5,000
        $this->buyOnLoan(3000);    // we owe them 3,000

        $this->assertEqualsWithDelta(5000.0, $this->balanceOf($this->customer), 0.01);
        $this->assertEqualsWithDelta(-3000.0, $this->balanceOf($this->supplier), 0.01);

        $this->offset(3000);

        // Only the difference is still outstanding, and it is on our side.
        $this->assertEqualsWithDelta(2000.0, $this->balanceOf($this->customer), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->balanceOf($this->supplier), 0.01);

        $this->assertEqualsWithDelta(
            0.0,
            $this->accountBalance('contra-clearing'),
            0.01,
            'The clearing account is the proof the two halves cancelled.',
        );
    }

    public function test_the_invoices_on_both_sides_actually_close(): void
    {
        $this->sellOnLoan(5000);
        $this->buyOnLoan(3000);

        $this->assertEqualsWithDelta(5000.0, $this->openTotal($this->customer, SettlementService::DIRECTION_IN), 0.01);
        $this->assertEqualsWithDelta(3000.0, $this->openTotal($this->supplier, SettlementService::DIRECTION_OUT), 0.01);

        $this->offset(3000);

        // This is what a journal entry could not do: settle the documents, not
        // just move the control accounts.
        $this->assertEqualsWithDelta(
            2000.0,
            $this->openTotal($this->customer, SettlementService::DIRECTION_IN),
            0.01,
            'The sale should be left owing only the difference.',
        );

        $this->assertEqualsWithDelta(
            0.0,
            $this->openTotal($this->supplier, SettlementService::DIRECTION_OUT),
            0.01,
            'The purchase should be fully settled.',
        );
    }

    public function test_receivables_and_payables_both_fall_by_the_offset(): void
    {
        $this->sellOnLoan(5000);
        $this->buyOnLoan(3000);

        $receivableBefore = $this->accountBalance('account-receivable');
        $payableBefore = $this->accountBalance('account-payable');

        $this->offset(3000);

        $this->assertEqualsWithDelta(
            $receivableBefore - 3000,
            $this->accountBalance('account-receivable'),
            0.01,
        );

        $this->assertEqualsWithDelta(
            $payableBefore + 3000,
            $this->accountBalance('account-payable'),
            0.01,
        );
    }

    public function test_it_refuses_the_wrong_kind_of_account(): void
    {
        $this->sellOnLoan(5000);
        $this->buyOnLoan(3000);

        $employee = Ledger::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'name' => 'Payroll party',
            'code' => 'EMP-1',
            'type' => LedgerType::EMPLOYEE->value,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ContraSettlementService::class)->offset(
            customer: $employee,
            supplier: $this->supplier,
            voucher: [
                'date' => now()->toDateString(),
                'branch_id' => $this->ctx['branch']->id,
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'amount' => 1000,
            ],
        );
    }

    public function test_offsetting_more_than_is_owed_is_refused_and_nothing_is_saved(): void
    {
        $this->sellOnLoan(5000);
        $this->buyOnLoan(3000);

        try {
            // We only owe them 3,000; 4,000 cannot be set off.
            $this->offset(4000);
            $this->fail('Offsetting more than the payable side holds must be refused.');
        } catch (\Throwable $e) {
            // The shape of the exception is the engine's business; what matters
            // is that nothing was left behind.
        }

        $this->assertEqualsWithDelta(5000.0, $this->balanceOf($this->customer), 0.01);
        $this->assertEqualsWithDelta(-3000.0, $this->balanceOf($this->supplier), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->accountBalance('contra-clearing'), 0.01);
    }
}

<?php

namespace Tests\Feature\Hr;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\PayrollStatus;
use App\Enums\TaxPeriod;
use App\Enums\TransactionStatus;
use App\Exceptions\Hr\PayrollException;
use App\Models\Hr\Attendance;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeLoan;
use App\Models\Hr\EmployeeLoanRepayment;
use App\Models\Hr\Payroll;
use App\Models\Hr\SalaryComponent;
use App\Models\Hr\SalaryStructure;
use App\Models\Hr\Shift;
use App\Models\Hr\TaxBracket;
use App\Models\Hr\TaxBracketSet;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Services\Hr\PayrollCalculationService;
use App\Services\Hr\PayrollPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Reversing a posted payroll, and recovering loans through one.
 *
 * A posted run is immutable, so correcting it means reversing and re-running.
 * The reversal has to undo everything the posting did — the voucher, the loan
 * repayments, the attendance lock — or the next run starts from a corrupted
 * position.
 */
class PayrollReversalFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Employee $employee;

    private const PERIOD_START = '2026-08-01';

    private const PERIOD_END = '2026-08-31';

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();

        $shift = Shift::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $this->employee = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'shift_id' => $shift->id,
            'joining_date' => '2024-01-01',
        ]);

        $set = TaxBracketSet::create([
            'name' => 'AF Monthly',
            'period' => TaxPeriod::Monthly->value,
            'effective_from' => '2005-01-01',
            'currency_id' => $this->ctx['currency']->id,
            'is_active' => true,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        foreach (TaxBracketSet::defaultAfghanMonthlyBrackets() as $bracket) {
            TaxBracket::create(array_merge($bracket, [
                'tax_bracket_set_id' => $set->id,
                'branch_id' => $this->ctx['branch']->id,
                'created_by' => $this->ctx['user']->id,
            ]));
        }

        foreach (SalaryComponent::defaultComponents() as $component) {
            SalaryComponent::create(array_merge(['affects_gross' => true, 'is_active' => true], $component, [
                'branch_id' => $this->ctx['branch']->id,
                'created_by' => $this->ctx['user']->id,
            ]));
        }

        SalaryStructure::create([
            'name' => 'Package',
            'employee_id' => $this->employee->id,
            'currency_id' => $this->ctx['currency']->id,
            'effective_from' => '2024-01-01',
            'basic_salary' => 50000,
            'pay_frequency' => 'monthly',
            'is_active' => true,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);
    }

    private function postPayroll(): Payroll
    {
        $payroll = Payroll::create([
            'number' => (string) Payroll::nextNumber(),
            'period_start' => self::PERIOD_START,
            'period_end' => self::PERIOD_END,
            'pay_date' => self::PERIOD_END,
            'period_label' => '1405-05',
            'pay_frequency' => 'monthly',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'status' => PayrollStatus::Draft->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $payroll = app(PayrollCalculationService::class)->calculate($payroll);

        $service = app(PayrollPostingService::class);
        $payroll = $service->transitionTo($payroll, PayrollStatus::PendingApproval);
        $payroll = $service->transitionTo($payroll, PayrollStatus::Approved);

        return $service->post($payroll);
    }

    private function activeLoan(float $principal = 12000, float $installment = 2000): EmployeeLoan
    {
        return EmployeeLoan::create([
            'number' => (string) EmployeeLoan::nextNumber(),
            'employee_id' => $this->employee->id,
            'loan_type' => LoanType::Loan->value,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'principal_amount' => $principal,
            'installment_amount' => $installment,
            'installments_count' => (int) ceil($principal / $installment),
            'deduct_from_payroll' => true,
            'issue_date' => '2026-07-01',
            'outstanding_amount' => $principal,
            'status' => LoanStatus::Active->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);
    }

    public function test_reversing_posts_a_mirror_and_flips_the_original(): void
    {
        $payroll = $this->postPayroll();
        $originalId = $payroll->transaction_id;

        $payroll = app(PayrollPostingService::class)->reverse($payroll, 'Wrong period');

        $this->assertSame(PayrollStatus::Reversed, $payroll->statusEnum());
        $this->assertNotNull($payroll->reversal_transaction_id);

        $original = Transaction::withoutGlobalScopes()->find($originalId);
        $this->assertSame(TransactionStatus::REVERSED->value, $original->status instanceof \BackedEnum ? $original->status->value : $original->status);
    }

    public function test_the_reversal_mirrors_the_original_amounts(): void
    {
        $payroll = $this->postPayroll();

        $originalCredit = TransactionLine::withoutGlobalScopes()
            ->where('transaction_id', $payroll->transaction_id)
            ->sum('base_credit');

        $payroll = app(PayrollPostingService::class)->reverse($payroll);

        $reversalDebit = TransactionLine::withoutGlobalScopes()
            ->where('transaction_id', $payroll->reversal_transaction_id)
            ->sum('base_debit');

        $this->assertEqualsWithDelta((float) $originalCredit, (float) $reversalDebit, 0.0001);
    }

    /**
     * The accrual and its mirror must cancel on the payable account.
     *
     * Both are counted, because a reversed transaction and its mirror are both
     * real GL facts — counting only the mirror would show the account owing
     * the reverse of what was accrued.
     */
    public function test_the_reversed_accrual_leaves_no_payable_balance(): void
    {
        $payroll = $this->postPayroll();

        app(PayrollPostingService::class)->reverse($payroll);

        $payableId = $this->ctx['accounts']['payroll-liabilities']->id;

        $net = TransactionLine::withoutGlobalScopes()
            ->join('transactions as t', 't.id', '=', 'transaction_lines.transaction_id')
            ->where('transaction_lines.account_id', $payableId)
            ->whereIn('t.status', [TransactionStatus::POSTED->value, TransactionStatus::REVERSED->value])
            ->selectRaw('COALESCE(SUM(transaction_lines.base_credit - transaction_lines.base_debit), 0) as net')
            ->value('net');

        $this->assertEqualsWithDelta(0.0, (float) $net, 0.0001);
    }

    /**
     * The employee's own statement must return to zero after a reversal.
     *
     * This is what the accounting-layer fix in LedgerStatementService is for:
     * reverse() flips the original to `reversed` and posts the mirror as
     * `posted`, so a statement filtering on `posted` alone saw only the mirror
     * and reported a phantom debit against the employee.
     */
    public function test_the_employee_statement_returns_to_zero_after_a_reversal(): void
    {
        $payroll = $this->postPayroll();
        $ledger = \App\Models\Ledger\Ledger::withoutGlobalScopes()
            ->find($this->employee->fresh()->ledger_id);

        $accrued = app(\App\Services\LedgerStatementService::class)->balancesByCurrency($ledger);
        $this->assertNotEmpty($accrued, 'The accrual should show on the employee statement.');

        app(PayrollPostingService::class)->reverse($payroll);

        $after = app(\App\Services\LedgerStatementService::class)->balancesByCurrency($ledger);

        // Fully-settled currencies are dropped from the balance card, so an
        // empty result IS the zero balance.
        $net = collect($after)->sum('net_balance');

        $this->assertEqualsWithDelta(0.0, (float) $net, 0.0001);
    }

    /**
     * A reversed salary must no longer be payable, which is what stops a
     * disbursement being raised against it.
     */
    public function test_a_reversed_accrual_is_no_longer_an_open_item(): void
    {
        $payroll = $this->postPayroll();
        $ledger = \App\Models\Ledger\Ledger::withoutGlobalScopes()
            ->find($this->employee->fresh()->ledger_id);

        $open = app(\App\Services\Accounting\SettlementService::class)
            ->openItems($ledger->id, null, \App\Services\Accounting\SettlementService::DIRECTION_OUT);
        $this->assertNotEmpty($open, 'An accrued salary should be payable.');

        app(PayrollPostingService::class)->reverse($payroll);

        $openAfter = app(\App\Services\Accounting\SettlementService::class)
            ->openItems($ledger->id, null, \App\Services\Accounting\SettlementService::DIRECTION_OUT);

        $this->assertEmpty($openAfter, 'A reversed salary must not remain payable.');
    }

    public function test_reversing_unlocks_the_attendance_it_froze(): void
    {
        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-08-17',
            'status' => 'present',
            'worked_hours' => 8,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $payroll = $this->postPayroll();
        $this->assertSame(1, Attendance::query()->where('payroll_id', $payroll->id)->count());

        app(PayrollPostingService::class)->reverse($payroll);

        $this->assertSame(0, Attendance::query()->whereNotNull('payroll_id')->count());
    }

    public function test_a_loan_instalment_is_recovered_and_reduces_net_pay(): void
    {
        $loan = $this->activeLoan(12000, 2000);

        $payroll = $this->postPayroll();
        $line = $payroll->lines()->firstOrFail();

        $recovery = $line->components->firstWhere('component_code', SalaryComponent::CODE_LOAN_RECOVERY);
        $this->assertNotNull($recovery);
        $this->assertEqualsWithDelta(2000, (float) $recovery->amount, 0.01);

        // 50,000 gross − 3,900 tax − 2,000 recovery.
        $this->assertEqualsWithDelta(44100, (float) $line->net_payable, 0.01);

        $this->assertEqualsWithDelta(10000, (float) $loan->fresh()->outstanding_amount, 0.01);
    }

    /**
     * Loan recovery is not a pay cut, so it must not reduce the tax base.
     */
    public function test_loan_recovery_does_not_reduce_taxable_income(): void
    {
        $this->activeLoan(12000, 2000);

        $line = $this->postPayroll()->lines()->firstOrFail();

        $this->assertEqualsWithDelta(50000, (float) $line->taxable_income, 0.01);
        $this->assertEqualsWithDelta(3900, (float) $line->tax_amount, 0.01);
    }

    public function test_reversing_removes_the_repayments_and_restores_the_balance(): void
    {
        $loan = $this->activeLoan(12000, 2000);

        $payroll = $this->postPayroll();
        $this->assertSame(1, EmployeeLoanRepayment::query()->where('employee_loan_id', $loan->id)->count());
        $this->assertEqualsWithDelta(10000, (float) $loan->fresh()->outstanding_amount, 0.01);

        app(PayrollPostingService::class)->reverse($payroll);

        $this->assertSame(0, EmployeeLoanRepayment::query()->where('employee_loan_id', $loan->id)->count());
        $this->assertEqualsWithDelta(12000, (float) $loan->fresh()->outstanding_amount, 0.01);
        $this->assertSame(LoanStatus::Active, $loan->fresh()->statusEnum());
    }

    public function test_a_final_instalment_settles_the_loan(): void
    {
        $loan = $this->activeLoan(2000, 2000);

        $this->postPayroll();

        $this->assertEqualsWithDelta(0, (float) $loan->fresh()->outstanding_amount, 0.01);
        $this->assertSame(LoanStatus::Settled, $loan->fresh()->statusEnum());
    }

    /**
     * The last instalment must not over-recover: an employee owing 500 with a
     * 2,000 instalment should have 500 taken, not 2,000.
     */
    public function test_the_last_instalment_is_capped_at_what_is_owed(): void
    {
        $loan = $this->activeLoan(2000, 2000);
        $loan->forceFill(['outstanding_amount' => 500])->save();

        $line = $this->postPayroll()->lines()->firstOrFail();

        $recovery = $line->components->firstWhere('component_code', SalaryComponent::CODE_LOAN_RECOVERY);

        $this->assertEqualsWithDelta(500, (float) $recovery->amount, 0.01);
    }

    public function test_a_run_can_be_reposted_after_a_reversal(): void
    {
        $payroll = $this->postPayroll();
        app(PayrollPostingService::class)->reverse($payroll);

        $fresh = $this->postPayroll();

        $this->assertSame(PayrollStatus::Posted, $fresh->statusEnum());
        $this->assertNotSame($payroll->id, $fresh->id);
    }

    public function test_reversing_an_unposted_payroll_throws(): void
    {
        $payroll = Payroll::create([
            'number' => (string) Payroll::nextNumber(),
            'period_start' => self::PERIOD_START,
            'period_end' => self::PERIOD_END,
            'status' => PayrollStatus::Draft->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $this->expectException(PayrollException::class);
        app(PayrollPostingService::class)->reverse($payroll);
    }

    /**
     * Reversing the accrual under a disbursement would leave the cash side
     * pointing at a liability that no longer exists.
     */
    public function test_reversing_is_refused_while_a_payslip_is_partly_paid(): void
    {
        $payroll = $this->postPayroll();

        $payroll->lines()->firstOrFail()->forceFill(['paid_amount' => 1000])->save();

        $this->expectException(PayrollException::class);
        app(PayrollPostingService::class)->reverse($payroll->fresh());
    }
}

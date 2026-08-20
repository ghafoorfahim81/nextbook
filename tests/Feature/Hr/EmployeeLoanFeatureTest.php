<?php

namespace Tests\Feature\Hr;

use App\Enums\LoanRepaymentSource;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\PayrollStatus;
use App\Enums\TaxPeriod;
use App\Exceptions\Hr\PayrollException;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeLoan;
use App\Models\Hr\Payroll;
use App\Models\Hr\SalaryComponent;
use App\Models\Hr\SalaryStructure;
use App\Models\Hr\Shift;
use App\Models\Hr\TaxBracket;
use App\Models\Hr\TaxBracketSet;
use App\Models\Transaction\TransactionLine;
use App\Services\Hr\EmployeeLoanService;
use App\Services\Hr\PayrollCalculationService;
use App\Services\Hr\PayrollPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Staff loans and advances.
 *
 * Two things are load-bearing. The control account must follow the KIND of
 * loan — an advance against next month's pay is not the same asset as a loan
 * that may outlive the employment — and the outstanding balance must be
 * DERIVED, so a reversed payroll restores it without anyone remembering to.
 */
class EmployeeLoanFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();

        $shift = Shift::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $this->employee = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'shift_id' => $shift->id,
            'employment_type' => 'permanent',
            'joining_date' => '2024-01-01',
        ]);
    }

    private function service(): EmployeeLoanService
    {
        return app(EmployeeLoanService::class);
    }

    private function accountId(string $slug): string
    {
        return $this->ctx['accounts'][$slug]->id;
    }

    private function loan(array $overrides = []): EmployeeLoan
    {
        return EmployeeLoan::create(array_merge([
            'number' => (string) EmployeeLoan::nextNumber(),
            'employee_id' => $this->employee->id,
            'loan_type' => LoanType::Loan->value,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'principal_amount' => 12000,
            'installment_amount' => 1000,
            'installments_count' => 12,
            'deduct_from_payroll' => true,
            'issue_date' => '2026-07-01',
            'status' => LoanStatus::Draft->value,
            'bank_account_id' => $this->accountId('cash-in-hand'),
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ], $overrides));
    }

    /** A loan taken all the way to active. */
    private function activeLoan(array $overrides = []): EmployeeLoan
    {
        $loan = $this->loan($overrides);
        $loan = $this->service()->approve($loan);

        return $this->service()->disburse($loan);
    }

    // ==================================================
    // APPROVAL
    // ==================================================

    public function test_a_loan_moves_through_submit_and_approve(): void
    {
        $loan = $this->loan();

        $loan = $this->service()->submit($loan);
        $this->assertSame(LoanStatus::PendingApproval, $loan->statusEnum());

        $loan = $this->service()->approve($loan);
        $this->assertSame(LoanStatus::Approved, $loan->statusEnum());
        $this->assertNotNull($loan->approved_at);
    }

    /**
     * Approving authorises the money; it does not move it. The person who
     * approves a staff loan is usually not the person who opens the safe.
     */
    public function test_approval_alone_posts_nothing(): void
    {
        $loan = $this->service()->approve($this->loan());

        $this->assertNull($loan->transaction_id);
        $this->assertEqualsWithDelta(0, (float) $loan->outstanding_amount, 0.01);
    }

    public function test_an_unapproved_loan_cannot_be_disbursed(): void
    {
        $this->expectException(PayrollException::class);

        $this->service()->disburse($this->loan());
    }

    public function test_a_rejected_loan_cannot_be_approved_afterwards(): void
    {
        $loan = $this->service()->reject($this->loan(), 'not eligible');
        $this->assertSame(LoanStatus::Cancelled, $loan->statusEnum());

        $this->expectException(PayrollException::class);
        $this->service()->approve($loan);
    }

    // ==================================================
    // DISBURSEMENT
    // ==================================================

    public function test_disbursing_a_loan_debits_the_receivable_and_credits_cash(): void
    {
        $loan = $this->activeLoan();

        $lines = TransactionLine::query()->where('transaction_id', $loan->transaction_id)->get();

        $receivable = $lines->firstWhere('account_id', $this->accountId('employee-loans-receivable'));
        $cash = $lines->firstWhere('account_id', $this->accountId('cash-in-hand'));

        $this->assertNotNull($receivable);
        $this->assertEqualsWithDelta(12000, (float) $receivable->debit, 0.01);
        $this->assertEqualsWithDelta(12000, (float) $cash->credit, 0.01);

        // Carries the employee's ledger, so the loan appears on their statement
        // the same way a customer balance would.
        $this->assertSame($this->employee->ledger_id, $receivable->ledger_id);
        $this->assertSame(LoanStatus::Active, $loan->statusEnum());
        $this->assertEqualsWithDelta(12000, (float) $loan->outstanding_amount, 0.01);
    }

    /**
     * An advance against next month's pay is a different asset from a loan that
     * may outlive the employment, and netting them makes either unreportable.
     */
    public function test_an_advance_uses_the_advances_account_not_the_loan_account(): void
    {
        $loan = $this->activeLoan(['loan_type' => LoanType::SalaryAdvance->value]);

        $lines = TransactionLine::query()->where('transaction_id', $loan->transaction_id)->get();

        $this->assertNotNull($lines->firstWhere('account_id', $this->accountId('employee-advances')));
        $this->assertNull($lines->firstWhere('account_id', $this->accountId('employee-loans-receivable')));
    }

    public function test_a_loan_cannot_be_disbursed_twice(): void
    {
        $loan = $this->activeLoan();

        $this->expectException(PayrollException::class);
        $this->service()->disburse($loan);
    }

    // ==================================================
    // CASH REPAYMENT
    // ==================================================

    public function test_a_cash_repayment_debits_cash_and_credits_the_receivable(): void
    {
        $loan = $this->activeLoan();

        $repayment = $this->service()->repayInCash($loan, [
            'date' => '2026-08-05',
            'amount' => 3000,
            'bank_account_id' => $this->accountId('cash-in-hand'),
        ]);

        $lines = TransactionLine::query()->where('transaction_id', $repayment->transaction_id)->get();

        $this->assertEqualsWithDelta(
            3000,
            (float) $lines->firstWhere('account_id', $this->accountId('cash-in-hand'))->debit,
            0.01
        );
        $this->assertEqualsWithDelta(
            3000,
            (float) $lines->firstWhere('account_id', $this->accountId('employee-loans-receivable'))->credit,
            0.01
        );

        $this->assertEqualsWithDelta(9000, (float) $loan->fresh()->outstanding_amount, 0.01);
        $this->assertSame(LoanRepaymentSource::Cash, $repayment->source);
    }

    public function test_repaying_the_last_of_it_settles_the_loan(): void
    {
        $loan = $this->activeLoan();

        $this->service()->repayInCash($loan, [
            'date' => '2026-08-05',
            'amount' => 12000,
            'bank_account_id' => $this->accountId('cash-in-hand'),
        ]);

        $loan = $loan->fresh();
        $this->assertEqualsWithDelta(0, (float) $loan->outstanding_amount, 0.01);
        $this->assertSame(LoanStatus::Settled, $loan->statusEnum());
    }

    /**
     * Refused rather than parked as a credit: handing back more than was
     * borrowed is a keying slip, and turning it into a payable hides it.
     */
    public function test_repaying_more_than_is_owed_is_refused(): void
    {
        $loan = $this->activeLoan();

        $this->expectException(PayrollException::class);
        $this->service()->repayInCash($loan, [
            'date' => '2026-08-05',
            'amount' => 15000,
            'bank_account_id' => $this->accountId('cash-in-hand'),
        ]);
    }

    public function test_an_undisbursed_loan_cannot_be_repaid(): void
    {
        $loan = $this->service()->approve($this->loan());

        $this->expectException(PayrollException::class);
        $this->service()->repayInCash($loan, [
            'date' => '2026-08-05',
            'amount' => 1000,
            'bank_account_id' => $this->accountId('cash-in-hand'),
        ]);
    }

    // ==================================================
    // WRITE-OFF
    // ==================================================

    public function test_writing_off_charges_the_remainder_to_staff_benefits(): void
    {
        $loan = $this->activeLoan();

        $this->service()->repayInCash($loan, [
            'date' => '2026-08-05',
            'amount' => 2000,
            'bank_account_id' => $this->accountId('cash-in-hand'),
        ]);

        $loan = $this->service()->writeOff($loan->fresh(), '2026-09-01', 'employee left');

        $writeOff = $loan->repayments()
            ->where('source', LoanRepaymentSource::WriteOff->value)
            ->firstOrFail();

        $lines = TransactionLine::query()->where('transaction_id', $writeOff->transaction_id)->get();

        $this->assertEqualsWithDelta(
            10000,
            (float) $lines->firstWhere('account_id', $this->accountId('staff-benefits-expense'))->debit,
            0.01
        );
        $this->assertEqualsWithDelta(0, (float) $loan->outstanding_amount, 0.01);

        // Not `settled` — a zero balance reached by giving up is not repayment,
        // and the loan statement has to be able to say which happened.
        $this->assertSame(LoanStatus::WrittenOff, $loan->statusEnum());
    }

    public function test_a_fully_repaid_loan_has_nothing_to_write_off(): void
    {
        $loan = $this->activeLoan();

        $this->service()->repayInCash($loan, [
            'date' => '2026-08-05',
            'amount' => 12000,
            'bank_account_id' => $this->accountId('cash-in-hand'),
        ]);

        $this->expectException(PayrollException::class);
        $this->service()->writeOff($loan->fresh(), '2026-09-01');
    }

    // ==================================================
    // PAYROLL RECOVERY
    // ==================================================

    public function test_payroll_recovers_an_instalment_and_reduces_the_balance(): void
    {
        $this->seedPayrollFixtures();
        $loan = $this->activeLoan();

        $payroll = $this->postPayroll();
        $line = $payroll->lines()->firstOrFail();

        // 50,000 gross, 3,900 tax, 1,000 instalment.
        $this->assertEqualsWithDelta(45100, (float) $line->net_payable, 0.01);
        $this->assertEqualsWithDelta(11000, (float) $loan->fresh()->outstanding_amount, 0.01);
    }

    /**
     * Recovering a loan is not a pay cut, so it must not reduce what is taxed.
     */
    public function test_loan_recovery_does_not_reduce_the_tax_base(): void
    {
        $this->seedPayrollFixtures();
        $this->activeLoan();

        $line = $this->postPayroll()->lines()->firstOrFail();

        $this->assertEqualsWithDelta(50000, (float) $line->taxable_income, 0.01);
        $this->assertEqualsWithDelta(3900, (float) $line->tax_amount, 0.01);
    }

    /**
     * The balance is derived from repayment rows, not decremented — which is
     * what makes a reversal restore it with no compensating entry.
     */
    public function test_reversing_the_payroll_restores_the_loan_balance(): void
    {
        $this->seedPayrollFixtures();
        $loan = $this->activeLoan();

        $payroll = $this->postPayroll();
        $this->assertEqualsWithDelta(11000, (float) $loan->fresh()->outstanding_amount, 0.01);

        app(PayrollPostingService::class)->reverse($payroll, 'correction');

        $loan = $loan->fresh();
        $this->assertEqualsWithDelta(12000, (float) $loan->outstanding_amount, 0.01);
        $this->assertSame(LoanStatus::Active, $loan->statusEnum());
        $this->assertCount(0, $loan->repayments()->get());
    }

    /**
     * The last instalment takes what is left, not a full instalment — nobody
     * should overpay a loan by rounding.
     */
    public function test_the_final_instalment_is_capped_at_what_is_owed(): void
    {
        $this->seedPayrollFixtures();
        $loan = $this->activeLoan([
            'principal_amount' => 1500,
            'installment_amount' => 1000,
            'installments_count' => 2,
        ]);

        $this->service()->repayInCash($loan, [
            'date' => '2026-07-15',
            'amount' => 1000,
            'bank_account_id' => $this->accountId('cash-in-hand'),
        ]);

        $line = $this->postPayroll()->lines()->firstOrFail();

        // Only 500 was left, so only 500 comes out — not the 1,000 instalment.
        $this->assertEqualsWithDelta(45600, (float) $line->net_payable, 0.01);
        $this->assertEqualsWithDelta(0, (float) $loan->fresh()->outstanding_amount, 0.01);
        $this->assertSame(LoanStatus::Settled, $loan->fresh()->statusEnum());
    }

    public function test_a_loan_marked_not_deductible_is_left_to_cash_repayment(): void
    {
        $this->seedPayrollFixtures();
        $loan = $this->activeLoan(['deduct_from_payroll' => false]);

        $line = $this->postPayroll()->lines()->firstOrFail();

        $this->assertEqualsWithDelta(46100, (float) $line->net_payable, 0.01);
        $this->assertEqualsWithDelta(12000, (float) $loan->fresh()->outstanding_amount, 0.01);
    }

    /**
     * A loan issued after the run's period should not be recovered from it.
     */
    public function test_a_loan_not_yet_due_for_deduction_is_skipped(): void
    {
        $this->seedPayrollFixtures();
        $loan = $this->activeLoan(['first_deduction_period' => '2026-12-01']);

        $line = $this->postPayroll()->lines()->firstOrFail();

        $this->assertEqualsWithDelta(46100, (float) $line->net_payable, 0.01);
        $this->assertEqualsWithDelta(12000, (float) $loan->fresh()->outstanding_amount, 0.01);
    }

    // ==================================================
    // FIXTURES
    // ==================================================

    private function seedPayrollFixtures(): void
    {
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
            'currency_id' => $this->employee->currency_id,
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
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'pay_date' => '2026-08-31',
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
}

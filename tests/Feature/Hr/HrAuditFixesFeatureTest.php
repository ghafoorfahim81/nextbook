<?php

namespace Tests\Feature\Hr;

use App\Enums\ApplicationStatus;
use App\Enums\JobOpeningStatus;
use App\Enums\PayrollStatus;
use App\Enums\SalaryComponentType;
use App\Enums\TaxPeriod;
use App\Exceptions\Hr\PayrollException;
use App\Models\Hr\Attendance;
use App\Models\Hr\Employee;
use App\Models\Hr\Interview;
use App\Models\Hr\InterviewPanelist;
use App\Models\Hr\JobApplication;
use App\Models\Hr\JobOpening;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Payroll;
use App\Models\Hr\SalaryComponent;
use App\Models\Hr\SalaryStructure;
use App\Models\Hr\SalaryStructureLine;
use App\Models\Hr\Shift;
use App\Models\Hr\TaxBracket;
use App\Models\Hr\TaxBracketSet;
use App\Models\Transaction\TransactionLine;
use App\Services\Hr\PayrollCalculationService;
use App\Services\Hr\PayrollPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Regressions for the findings raised in the HR modules audit.
 *
 * Each test names the defect it pins rather than the feature it exercises, so
 * a future change that reintroduces one of these fails with an obvious reason.
 */
class HrAuditFixesFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Employee $employee;

    private const START = '2026-08-01';

    private const END = '2026-08-31';

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

        $this->seedPayrollFixtures();
    }

    // ==================================================
    // 1. PAYROLL SCOPE OVERLAP  (paid twice)
    // ==================================================

    /**
     * A company-wide run and a department run over the same month both cover
     * the same staff. The old guard compared department_id for equality, so
     * NULL never matched a department and both runs were allowed.
     */
    public function test_a_department_run_cannot_overlap_a_company_wide_run(): void
    {
        $this->post(route('payrolls.store'), $this->payrollPayload())
            ->assertSessionHasNoErrors();

        $department = \App\Models\Administration\Department::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
        ]);

        $this->post(route('payrolls.store'), $this->payrollPayload([
            'department_id' => $department->id,
        ]))->assertSessionHasErrors('period_start');
    }

    /** And the same the other way round: department first, company-wide second. */
    public function test_a_company_wide_run_cannot_overlap_a_department_run(): void
    {
        $department = \App\Models\Administration\Department::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
        ]);

        $this->post(route('payrolls.store'), $this->payrollPayload([
            'department_id' => $department->id,
        ]))->assertSessionHasNoErrors();

        $this->post(route('payrolls.store'), $this->payrollPayload())
            ->assertSessionHasErrors('period_start');
    }

    /** Two genuinely disjoint departments may still both be run. */
    public function test_two_different_departments_do_not_conflict(): void
    {
        $a = \App\Models\Administration\Department::factory()->create(['branch_id' => $this->ctx['branch']->id]);
        $b = \App\Models\Administration\Department::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $this->post(route('payrolls.store'), $this->payrollPayload(['department_id' => $a->id]))
            ->assertSessionHasNoErrors();

        $this->post(route('payrolls.store'), $this->payrollPayload(['department_id' => $b->id]))
            ->assertSessionHasNoErrors();
    }

    /**
     * The authoritative guard: even if two runs slip past the scope check,
     * calculating the second one refuses once it sees the same employee
     * already has a live payslip for overlapping days.
     */
    public function test_calculating_refuses_an_employee_already_on_another_live_payroll(): void
    {
        $first = $this->makePayroll();
        app(PayrollCalculationService::class)->calculate($first);

        $second = $this->makePayroll(['number' => (string) Payroll::nextNumber()]);

        $this->expectException(PayrollException::class);
        app(PayrollCalculationService::class)->calculate($second);
    }

    /** A reversed run frees the period, which is what reversal is for. */
    public function test_a_reversed_run_does_not_block_recalculation(): void
    {
        $first = $this->postedPayroll();
        app(PayrollPostingService::class)->reverse($first, 'correction');

        $second = $this->makePayroll(['number' => (string) Payroll::nextNumber()]);

        $calculated = app(PayrollCalculationService::class)->calculate($second);
        $this->assertCount(1, $calculated->lines);
    }

    // ==================================================
    // 2. PRORATION  (overpaid partial periods)
    // ==================================================

    /**
     * A joiner mid-period was being paid a full month's basic.
     */
    public function test_a_mid_period_joiner_is_paid_only_for_days_employed(): void
    {
        $this->employee->update(['joining_date' => '2026-08-17']);

        $line = app(PayrollCalculationService::class)
            ->calculate($this->makePayroll())
            ->lines()
            ->firstOrFail();

        // Sat–Thu shift: 27 working days in August 2026, 11 of them from the
        // 17th onward. 50,000 x 11/27 = 20,370.37.
        $this->assertGreaterThan(15000, (float) $line->basic_salary);
        $this->assertLessThan(25000, (float) $line->basic_salary);
        $this->assertLessThan(
            50000,
            (float) $line->basic_salary,
            'A mid-month joiner must not receive a full month of basic pay.'
        );
    }

    /** And a leaver mid-period, symmetrically. */
    public function test_a_mid_period_leaver_is_paid_only_for_days_employed(): void
    {
        $this->employee->update(['separation_date' => '2026-08-10']);

        $line = app(PayrollCalculationService::class)
            ->calculate($this->makePayroll())
            ->lines()
            ->firstOrFail();

        $this->assertLessThan(
            50000,
            (float) $line->basic_salary,
            'Someone who left on the 10th must not receive a full month of basic pay.'
        );
    }

    /** Anyone employed throughout is untouched by the proration. */
    public function test_a_full_period_employee_still_receives_full_basic(): void
    {
        $line = app(PayrollCalculationService::class)
            ->calculate($this->makePayroll())
            ->lines()
            ->firstOrFail();

        $this->assertEqualsWithDelta(50000, (float) $line->basic_salary, 0.01);
    }

    /**
     * `is_prorated` was configurable but never read.
     */
    public function test_a_prorated_allowance_shrinks_for_a_partial_month(): void
    {
        $this->employee->update(['joining_date' => '2026-08-17']);

        $transport = SalaryComponent::create([
            'name' => 'Transport',
            'code' => 'TRANSPORT',
            'component_type' => SalaryComponentType::Earning->value,
            'calculation_type' => 'fixed',
            'amount' => 3000,
            'is_prorated' => true,
            'is_taxable' => true,
            'affects_gross' => true,
            'is_active' => true,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        SalaryStructureLine::create([
            'salary_structure_id' => SalaryStructure::query()->firstOrFail()->id,
            'salary_component_id' => $transport->id,
            'calculation_type' => 'fixed',
            'amount' => 3000,
            'sequence' => 1,
            'branch_id' => $this->ctx['branch']->id,
        ]);

        $line = app(PayrollCalculationService::class)
            ->calculate($this->makePayroll())
            ->lines()
            ->firstOrFail();

        $row = $line->components->firstWhere('component_code', 'TRANSPORT');

        $this->assertNotNull($row);
        $this->assertLessThan(3000, (float) $row->amount, 'A prorated allowance must shrink.');
    }

    // ==================================================
    // 3. GL ROUTING  (employer cost / remittable deductions)
    // ==================================================

    /**
     * An employer contribution is an EXTRA company cost. It was falling into
     * the catch-all deduction branch and REDUCING salary expense, moving the
     * profit and loss by twice the amount in the wrong direction.
     */
    public function test_an_employer_contribution_adds_expense_and_credits_a_liability(): void
    {
        $this->attachComponent([
            'name' => 'Pension (employer)',
            'code' => 'PENSION_ER',
            'component_type' => SalaryComponentType::EmployerContribution->value,
            'amount' => 2500,
            'account_id' => $this->accountId('staff-benefits-expense'),
            'liability_account_id' => $this->accountId('payroll-liabilities'),
        ]);

        $payroll = $this->postedPayroll();
        $lines = TransactionLine::query()->where('transaction_id', $payroll->transaction_id)->get();

        $expense = $lines->firstWhere('account_id', $this->accountId('staff-benefits-expense'));

        $this->assertNotNull($expense, 'The contribution must reach an expense account.');
        $this->assertEqualsWithDelta(
            2500,
            (float) $expense->debit,
            0.01,
            'An employer contribution must DEBIT expense, not reduce it.'
        );

        $this->assertEqualsWithDelta(
            (float) $lines->sum('base_debit'),
            (float) $lines->sum('base_credit'),
            0.0001
        );
    }

    /** It must not touch the employee's own pay. */
    public function test_an_employer_contribution_does_not_reduce_take_home_pay(): void
    {
        $this->attachComponent([
            'name' => 'Pension (employer)',
            'code' => 'PENSION_ER',
            'component_type' => SalaryComponentType::EmployerContribution->value,
            'amount' => 2500,
            'account_id' => $this->accountId('staff-benefits-expense'),
            'liability_account_id' => $this->accountId('payroll-liabilities'),
        ]);

        $line = app(PayrollCalculationService::class)
            ->calculate($this->makePayroll())
            ->lines()
            ->firstOrFail();

        // 50,000 gross, 3,900 tax — the contribution is invisible to the employee.
        $this->assertEqualsWithDelta(46100, (float) $line->net_payable, 0.01);
    }

    /**
     * A remittable deduction is money withheld to pass on. It was reducing
     * salary expense, leaving no record of what the company owed the fund.
     */
    public function test_a_remittable_deduction_credits_a_liability_instead_of_reducing_expense(): void
    {
        $this->attachComponent([
            'name' => 'Social security',
            'code' => 'SSF',
            'component_type' => SalaryComponentType::Deduction->value,
            'amount' => 1500,
            'is_remittable' => true,
            'liability_account_id' => $this->accountId('salary-tax-payable'),
        ]);

        $payroll = $this->postedPayroll();
        $lines = TransactionLine::query()->where('transaction_id', $payroll->transaction_id)->get();

        // Wage tax and the withheld amount are separate lines on the same
        // account — legal, and easier to read on the voucher — so sum them
        // rather than expecting one merged line.
        $credited = $lines
            ->where('account_id', $this->accountId('salary-tax-payable'))
            ->sum(fn ($line) => (float) $line->credit);

        $this->assertGreaterThan(
            3900,
            $credited,
            'The withheld amount must be credited to a liability, not netted off expense.'
        );

        $this->assertEqualsWithDelta(
            (float) $lines->sum('base_debit'),
            (float) $lines->sum('base_credit'),
            0.0001
        );
    }

    /** A plain deduction still reduces expense — the company never owed it. */
    public function test_a_non_remittable_deduction_still_reduces_expense(): void
    {
        $this->attachComponent([
            'name' => 'Canteen',
            'code' => 'CANTEEN',
            'component_type' => SalaryComponentType::Deduction->value,
            'amount' => 800,
            'is_remittable' => false,
        ]);

        $payroll = $this->postedPayroll();
        $lines = TransactionLine::query()->where('transaction_id', $payroll->transaction_id)->get();

        $this->assertEqualsWithDelta(
            (float) $lines->sum('base_debit'),
            (float) $lines->sum('base_credit'),
            0.0001
        );
    }

    // ==================================================
    // 4. BRANCH-SCOPED VALIDATION  (tenancy)
    // ==================================================

    /**
     * `exists:employees,id` passed for ANY branch's employee. The policy check
     * authorises the ACTION, not the id inside the payload, so a crafted
     * request could attach another tenant's employee to a local record.
     */
    public function test_another_branch_employee_is_rejected_by_validation(): void
    {
        $other = $this->bootstrapErpContext();
        $foreign = Employee::factory()->create(['branch_id' => $other['branch']->id]);

        // Act as the original branch again.
        $this->actingAs($this->ctx['user']);
        app()->instance('active_branch_id', $this->ctx['branch']->id);

        $this->post(route('employee-loans.store'), [
            'employee_id' => $foreign->id,
            'loan_type' => 'loan',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'principal_amount' => 5000,
            'installment_amount' => 1000,
            'installments_count' => 5,
            'issue_date' => '2026-08-01',
        ])->assertSessionHasErrors('employee_id');
    }

    // ==================================================
    // 5. INTERVIEW PANELIST BINDING  (authorization)
    // ==================================================

    /**
     * Both models were resolved independently from the URL, so feedback could
     * be written onto a panelist belonging to a different interview.
     */
    public function test_feedback_cannot_be_written_onto_another_interviews_panelist(): void
    {
        [$interviewA, $panelistA] = $this->makeInterview('Candidate A');
        [$interviewB] = $this->makeInterview('Candidate B');

        $this->patch(route('interviews.feedback', [
            'interview' => $interviewB->id,
            'panelist' => $panelistA->id,
        ]), ['score' => 9])->assertNotFound();

        $this->assertNull($panelistA->fresh()->score);
    }

    public function test_feedback_on_the_correct_panelist_still_works(): void
    {
        [$interview, $panelist] = $this->makeInterview('Candidate A');

        $this->patch(route('interviews.feedback', [
            'interview' => $interview->id,
            'panelist' => $panelist->id,
        ]), ['score' => 9])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(9, (float) $panelist->fresh()->score, 0.01);
    }

    // ==================================================
    // 6. ROSTER vs APPROVED LEAVE
    // ==================================================

    /**
     * The roster could overwrite a day generated by an approved leave request,
     * leaving the leave still deducted from the balance while payroll counted
     * the day as worked.
     */
    public function test_the_roster_cannot_overwrite_an_approved_leave_day(): void
    {
        $type = LeaveType::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'is_paid' => true,
        ]);

        $request = LeaveRequest::create([
            'number' => (string) LeaveRequest::nextNumber(),
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'from_date' => '2026-08-05',
            'to_date' => '2026-08-05',
            'days' => 1,
            'status' => 'approved',
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-08-05',
            'status' => 'on_leave',
            'leave_request_id' => $request->id,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $this->post(route('attendances.roster.store'), [
            'date' => '2026-08-05',
            'rows' => [
                ['employee_id' => $this->employee->id, 'status' => 'present'],
            ],
        ])->assertSessionHasErrors();

        // `status` is cast, so compare the enum's value rather than the enum.
        $this->assertSame('on_leave', Attendance::query()
            ->where('employee_id', $this->employee->id)
            ->whereDate('date', '2026-08-05')
            ->value('status')?->value);
    }

    // ==================================================
    // 7. EMPLOYEE DELETION GUARD
    // ==================================================

    /**
     * Deleting an employee took their companion ledger with it while payslips
     * and loans stayed behind, pointing at an account that no longer existed.
     */
    public function test_an_employee_with_payslips_cannot_be_deleted(): void
    {
        $this->postedPayroll();

        $this->assertFalse($this->employee->fresh()->canBeDeleted());

        $this->delete(route('employees.destroy', $this->employee))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('employees', [
            'id' => $this->employee->id,
            'deleted_at' => null,
        ]);
    }

    // ==================================================
    // FIXTURES
    // ==================================================

    private function accountId(string $slug): string
    {
        return $this->ctx['accounts'][$slug]->id;
    }

    private function payrollPayload(array $overrides = []): array
    {
        return array_merge([
            'period_start' => self::START,
            'period_end' => self::END,
            'pay_date' => self::END,
            'pay_frequency' => 'monthly',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
        ], $overrides);
    }

    private function makePayroll(array $overrides = []): Payroll
    {
        return Payroll::create(array_merge([
            'number' => (string) Payroll::nextNumber(),
            'period_start' => self::START,
            'period_end' => self::END,
            'pay_date' => self::END,
            'period_label' => '1405-05',
            'pay_frequency' => 'monthly',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'status' => PayrollStatus::Draft->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ], $overrides));
    }

    private function postedPayroll(): Payroll
    {
        $payroll = app(PayrollCalculationService::class)->calculate($this->makePayroll());

        $service = app(PayrollPostingService::class);
        $payroll = $service->transitionTo($payroll, PayrollStatus::PendingApproval);
        $payroll = $service->transitionTo($payroll, PayrollStatus::Approved);

        return $service->post($payroll);
    }

    /** Add a component to the employee's structure. */
    private function attachComponent(array $attributes): SalaryComponent
    {
        $component = SalaryComponent::create(array_merge([
            'calculation_type' => 'fixed',
            'is_taxable' => false,
            'affects_gross' => false,
            'is_prorated' => false,
            'is_active' => true,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ], $attributes));

        SalaryStructureLine::create([
            'salary_structure_id' => SalaryStructure::query()->firstOrFail()->id,
            'salary_component_id' => $component->id,
            'calculation_type' => 'fixed',
            'amount' => $attributes['amount'],
            'sequence' => 5,
            'branch_id' => $this->ctx['branch']->id,
        ]);

        return $component;
    }

    /** @return array{0: Interview, 1: InterviewPanelist} */
    private function makeInterview(string $candidate): array
    {
        $opening = JobOpening::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'status' => JobOpeningStatus::Published->value,
        ]);

        $application = JobApplication::factory()->create([
            'job_opening_id' => $opening->id,
            'branch_id' => $this->ctx['branch']->id,
            'full_name' => $candidate,
            'status' => ApplicationStatus::Shortlisted->value,
        ]);

        $interview = Interview::create([
            'job_application_id' => $application->id,
            'round' => 1,
            'interview_type' => 'in_person',
            'scheduled_at' => '2026-08-25 10:00:00',
            'status' => 'scheduled',
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $panelist = InterviewPanelist::create([
            'interview_id' => $interview->id,
            'employee_id' => $this->employee->id,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        return [$interview, $panelist];
    }

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
}

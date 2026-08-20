<?php

namespace Tests\Feature\Hr;

use App\Enums\ApplicationStatus;
use App\Enums\JobOpeningStatus;
use App\Enums\LoanStatus;
use App\Enums\PayrollStatus;
use App\Enums\TaxPeriod;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeLoan;
use App\Models\Hr\JobApplication;
use App\Models\Hr\JobOpening;
use App\Models\Hr\Payroll;
use App\Models\Hr\SalaryComponent;
use App\Models\Hr\SalaryStructure;
use App\Models\Hr\Shift;
use App\Models\Hr\TaxBracket;
use App\Models\Hr\TaxBracketSet;
use App\Services\Hr\EmployeeLoanService;
use App\Services\Hr\PayrollCalculationService;
use App\Services\Hr\PayrollPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The phase 3 endpoints, over HTTP.
 *
 * The services underneath are covered elsewhere; what this pins is the wiring
 * — routes reachable, policies satisfied, Inertia components resolvable, and
 * the form requests actually refusing what they claim to. A controller that
 * 500s on its own index is the failure mode this catches.
 */
class PayrollHttpFeatureTest extends TestCase
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

    // ==================================================
    // EVERY INDEX RENDERS
    // ==================================================

    /**
     * The cheapest useful test in the module: a controller that 500s on its
     * own list page fails here rather than in front of a user.
     */
    public function test_every_phase_three_index_renders(): void
    {
        $pages = [
            'salary-components.index' => 'Hr/SalaryComponents/Index',
            'salary-structures.index' => 'Hr/SalaryStructures/Index',
            'tax-bracket-sets.index' => 'Hr/TaxBracketSets/Index',
            'payrolls.index' => 'Hr/Payrolls/Index',
            'salary-payments.index' => 'Hr/SalaryPayments/Index',
            'employee-loans.index' => 'Hr/EmployeeLoans/Index',
            'job-openings.index' => 'Hr/JobOpenings/Index',
            'job-applications.index' => 'Hr/JobApplications/Index',
            'interviews.index' => 'Hr/Interviews/Index',
        ];

        foreach ($pages as $route => $component) {
            $this->get(route($route))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component($component));
        }
    }

    public function test_the_create_pages_render(): void
    {
        foreach (['payrolls.create', 'salary-payments.create', 'employee-loans.create', 'salary-structures.create'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    // ==================================================
    // SALARY COMPONENTS
    // ==================================================

    public function test_a_percentage_component_without_a_percentage_is_rejected(): void
    {
        $this->post(route('salary-components.store'), [
            'name' => 'Transport',
            'code' => 'TRANSPORT',
            'component_type' => 'earning',
            'calculation_type' => 'percent_of_basic',
        ])->assertSessionHasErrors('percentage');
    }

    public function test_a_component_is_created(): void
    {
        $this->post(route('salary-components.store'), [
            'name' => 'Transport',
            'code' => 'TRANSPORT',
            'component_type' => 'earning',
            'calculation_type' => 'fixed',
            'amount' => 2000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('salary_components', ['code' => 'TRANSPORT']);
    }

    /**
     * The code is what the payroll engine matches BASIC and WITHHOLDING_TAX
     * on. Renaming is fine; re-coding would silently detach it.
     */
    public function test_a_system_components_code_cannot_be_changed(): void
    {
        $component = SalaryComponent::create([
            'name' => 'Basic Salary',
            'code' => 'BASIC',
            'component_type' => 'earning',
            'calculation_type' => 'fixed',
            'is_system' => true,
            'is_active' => true,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $this->put(route('salary-components.update', $component), [
            'name' => 'Base Pay',
            'code' => 'SOMETHING_ELSE',
            'component_type' => 'deduction',
            'calculation_type' => 'fixed',
            'amount' => 0,
        ])->assertSessionHasNoErrors();

        $component->refresh();
        $this->assertSame('Base Pay', $component->name);
        $this->assertSame('BASIC', $component->code);
        $this->assertSame('earning', $component->component_type->value);
    }

    public function test_a_system_component_cannot_be_deleted(): void
    {
        $component = SalaryComponent::create([
            'name' => 'Basic Salary',
            'code' => 'BASIC',
            'component_type' => 'earning',
            'calculation_type' => 'fixed',
            'is_system' => true,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $this->delete(route('salary-components.destroy', $component))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('salary_components', ['id' => $component->id, 'deleted_at' => null]);
    }

    // ==================================================
    // TAX TABLES
    // ==================================================

    public function test_a_tax_table_with_a_gap_is_rejected(): void
    {
        $this->post(route('tax-bracket-sets.store'), $this->taxPayload([
            ['sequence' => 1, 'from_amount' => 0, 'to_amount' => 5000, 'fixed_amount' => 0, 'rate' => 0],
            // Starts at 6,000, not 5,000 — income between them is taxed by no
            // band at all.
            ['sequence' => 2, 'from_amount' => 6000, 'to_amount' => null, 'fixed_amount' => 0, 'rate' => 10],
        ]))->assertSessionHasErrors('brackets.1.from_amount');
    }

    public function test_a_tax_table_whose_top_band_has_a_ceiling_is_rejected(): void
    {
        $this->post(route('tax-bracket-sets.store'), $this->taxPayload([
            ['sequence' => 1, 'from_amount' => 0, 'to_amount' => 5000, 'fixed_amount' => 0, 'rate' => 0],
            ['sequence' => 2, 'from_amount' => 5000, 'to_amount' => 100000, 'fixed_amount' => 0, 'rate' => 10],
        ]))->assertSessionHasErrors('brackets.1.to_amount');
    }

    public function test_a_tax_table_not_starting_at_zero_is_rejected(): void
    {
        $this->post(route('tax-bracket-sets.store'), $this->taxPayload([
            ['sequence' => 1, 'from_amount' => 1000, 'to_amount' => null, 'fixed_amount' => 0, 'rate' => 10],
        ]))->assertSessionHasErrors('brackets.0.from_amount');
    }

    public function test_a_contiguous_tax_table_is_accepted(): void
    {
        $this->post(route('tax-bracket-sets.store'), $this->taxPayload(
            TaxBracketSet::defaultAfghanMonthlyBrackets()
        ))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tax_bracket_sets', ['name' => 'AF Monthly 1405']);
        $this->assertSame(4, TaxBracket::query()->count());
    }

    /**
     * The bracket form answers "what would someone on 50,000 pay" while the
     * user is still editing the bands — a rate table is easy to get subtly
     * wrong and hard to read back.
     */
    public function test_the_tax_preview_computes_against_unsaved_brackets(): void
    {
        $response = $this->postJson(route('tax-bracket-sets.preview'), [
            'income' => 50000,
            'brackets' => TaxBracketSet::defaultAfghanMonthlyBrackets(),
        ]);

        $response->assertOk();
        // 150 + 10% of (50,000 − 12,500) = 3,900.
        $this->assertEqualsWithDelta(3900, (float) $response->json('tax'), 0.01);
        $this->assertEqualsWithDelta(46100, (float) $response->json('net'), 0.01);
    }

    private function taxPayload(array $brackets): array
    {
        return [
            'name' => 'AF Monthly 1405',
            'jurisdiction' => 'AF',
            'period' => TaxPeriod::Monthly->value,
            'effective_from' => '2026-03-21',
            'currency_id' => $this->ctx['currency']->id,
            'is_active' => true,
            'brackets' => $brackets,
        ];
    }

    // ==================================================
    // SALARY STRUCTURES
    // ==================================================

    public function test_a_structure_attached_to_nobody_is_rejected(): void
    {
        $this->post(route('salary-structures.store'), [
            'name' => 'Orphan package',
            'currency_id' => $this->ctx['currency']->id,
            'effective_from' => '2026-01-01',
            'basic_salary' => 30000,
            'pay_frequency' => 'monthly',
        ])->assertSessionHasErrors('employee_id');
    }

    public function test_a_structure_is_created_with_its_lines(): void
    {
        $component = SalaryComponent::create([
            'name' => 'Transport',
            'code' => 'TRANSPORT',
            'component_type' => 'earning',
            'calculation_type' => 'fixed',
            'amount' => 2000,
            'is_active' => true,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $this->post(route('salary-structures.store'), [
            'name' => 'Package',
            'employee_id' => $this->employee->id,
            'currency_id' => $this->ctx['currency']->id,
            'effective_from' => '2026-01-01',
            'basic_salary' => 30000,
            'pay_frequency' => 'monthly',
            'lines' => [
                ['salary_component_id' => $component->id, 'amount' => 2000, 'sequence' => 1],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $structure = SalaryStructure::firstOrFail();
        $this->assertCount(1, $structure->lines);
    }

    public function test_the_same_component_twice_in_a_structure_is_rejected(): void
    {
        $component = SalaryComponent::create([
            'name' => 'Transport',
            'code' => 'TRANSPORT',
            'component_type' => 'earning',
            'calculation_type' => 'fixed',
            'amount' => 2000,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $this->post(route('salary-structures.store'), [
            'name' => 'Package',
            'employee_id' => $this->employee->id,
            'currency_id' => $this->ctx['currency']->id,
            'effective_from' => '2026-01-01',
            'basic_salary' => 30000,
            'pay_frequency' => 'monthly',
            'lines' => [
                ['salary_component_id' => $component->id, 'amount' => 2000],
                ['salary_component_id' => $component->id, 'amount' => 3000],
            ],
        ])->assertSessionHasErrors('lines');
    }

    // ==================================================
    // PAYROLL LIFECYCLE
    // ==================================================

    public function test_a_payroll_is_created_and_shown(): void
    {
        $this->post(route('payrolls.store'), $this->payrollPayload())
            ->assertSessionHasNoErrors();

        $payroll = Payroll::firstOrFail();

        $this->get(route('payrolls.show', $payroll))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Hr/Payrolls/Show')
                // JsonResource wraps in `data`, which is why every Show page reads
                // `props.x?.data ?? props.x`.
                ->where('payroll.data.status', PayrollStatus::Draft->value));
    }

    /**
     * A second live run over the same period would pay everyone twice.
     */
    public function test_an_overlapping_payroll_period_is_rejected(): void
    {
        $this->post(route('payrolls.store'), $this->payrollPayload())->assertSessionHasNoErrors();

        $this->post(route('payrolls.store'), $this->payrollPayload([
            'period_start' => '2026-08-15',
            'period_end' => '2026-09-15',
        ]))->assertSessionHasErrors('period_start');
    }

    public function test_a_reversed_period_can_be_run_again(): void
    {
        $this->seedPayrollFixtures();
        $payroll = $this->postedPayroll();

        app(PayrollPostingService::class)->reverse($payroll, 'correction');

        // Re-running a corrected period is exactly the workflow reversal
        // exists for, so the overlap guard must not block it.
        $this->post(route('payrolls.store'), $this->payrollPayload())
            ->assertSessionHasNoErrors();
    }

    public function test_calculating_builds_the_payslips(): void
    {
        $this->seedPayrollFixtures();

        $this->post(route('payrolls.store'), $this->payrollPayload());
        $payroll = Payroll::firstOrFail();

        $this->patch(route('payrolls.calculate', $payroll))->assertSessionHasNoErrors();

        $payroll->refresh();
        $this->assertSame(PayrollStatus::Calculated, $payroll->statusEnum());
        $this->assertCount(1, $payroll->lines);
    }

    /**
     * Approving is a separate authority from preparing. Posting straight from
     * `calculated` is refused by the state machine.
     */
    public function test_posting_without_approval_is_refused(): void
    {
        $this->seedPayrollFixtures();

        $this->post(route('payrolls.store'), $this->payrollPayload());
        $payroll = Payroll::firstOrFail();
        $this->patch(route('payrolls.calculate', $payroll));

        $this->patch(route('payrolls.transition', $payroll), [
            'status' => PayrollStatus::Posted->value,
        ])->assertSessionHas('error');

        $this->assertSame(PayrollStatus::Calculated, $payroll->fresh()->statusEnum());
    }

    public function test_a_run_can_be_walked_to_posted(): void
    {
        $this->seedPayrollFixtures();

        $this->post(route('payrolls.store'), $this->payrollPayload());
        $payroll = Payroll::firstOrFail();
        $this->patch(route('payrolls.calculate', $payroll));

        foreach ([PayrollStatus::PendingApproval, PayrollStatus::Approved, PayrollStatus::Posted] as $status) {
            $this->patch(route('payrolls.transition', $payroll), ['status' => $status->value])
                ->assertSessionHasNoErrors();
        }

        $payroll->refresh();
        $this->assertSame(PayrollStatus::Posted, $payroll->statusEnum());
        $this->assertNotNull($payroll->transaction_id);
    }

    public function test_a_posted_payroll_cannot_be_edited_or_deleted(): void
    {
        $this->seedPayrollFixtures();
        $payroll = $this->postedPayroll();

        $this->get(route('payrolls.edit', $payroll))->assertRedirect(route('payrolls.show', $payroll));

        $this->delete(route('payrolls.destroy', $payroll))->assertSessionHas('error');
        $this->assertDatabaseHas('payrolls', ['id' => $payroll->id, 'deleted_at' => null]);
    }

    public function test_a_payslip_can_be_printed(): void
    {
        $this->seedPayrollFixtures();
        $payroll = $this->postedPayroll();
        $line = $payroll->lines()->firstOrFail();

        $this->get(route('payrolls.payslip', ['payroll' => $payroll, 'line' => $line->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Hr/Payrolls/Payslip')
                ->where('payslip.data.employee_code', $this->employee->code));
    }

    // ==================================================
    // SALARY PAYMENTS
    // ==================================================

    public function test_open_payslips_are_offered_for_the_payment_form(): void
    {
        $this->seedPayrollFixtures();
        $this->postedPayroll();

        $response = $this->getJson(route('salary-payments.open-payslips', [
            'employee_id' => $this->employee->id,
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json('open_items'));
        $this->assertEqualsWithDelta(46100, (float) $response->json('open_items.0.remaining_amount'), 0.01);
    }

    public function test_a_salary_payment_is_posted(): void
    {
        $this->seedPayrollFixtures();
        $this->postedPayroll();

        $this->post(route('salary-payments.store'), [
            'employee_id' => $this->employee->id,
            'date' => '2026-08-31',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'amount' => 46100,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('salary_payments', ['employee_id' => $this->employee->id]);
    }

    public function test_allocating_more_than_the_payment_is_rejected(): void
    {
        $this->seedPayrollFixtures();
        $payroll = $this->postedPayroll();
        $line = $payroll->lines()->firstOrFail();

        $this->post(route('salary-payments.store'), [
            'employee_id' => $this->employee->id,
            'date' => '2026-08-31',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'amount' => 1000,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'allocations' => [
                ['target_line_id' => $line->liability_line_id, 'amount' => 5000],
            ],
        ])->assertSessionHasErrors('allocations');
    }

    // ==================================================
    // LOANS
    // ==================================================

    public function test_a_loan_goes_from_draft_to_active(): void
    {
        $this->post(route('employee-loans.store'), $this->loanPayload())->assertSessionHasNoErrors();

        $loan = EmployeeLoan::firstOrFail();

        $this->patch(route('employee-loans.approve', $loan))->assertSessionHasNoErrors();
        $this->patch(route('employee-loans.disburse', $loan), [
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
        ])->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame(LoanStatus::Active, $loan->statusEnum());
        $this->assertNotNull($loan->transaction_id);
    }

    public function test_instalments_that_do_not_cover_the_loan_are_rejected(): void
    {
        $this->post(route('employee-loans.store'), $this->loanPayload([
            'principal_amount' => 12000,
            'installment_amount' => 500,
            'installments_count' => 6,
        ]))->assertSessionHasErrors('installment_amount');
    }

    public function test_a_disbursed_loan_cannot_be_edited(): void
    {
        $loan = $this->activeLoan();

        $this->get(route('employee-loans.edit', $loan))
            ->assertRedirect(route('employee-loans.show', $loan));

        // A schedule that is internally consistent, so the immutability check
        // is what refuses it rather than the instalment validator.
        $this->put(route('employee-loans.update', $loan), $this->loanPayload([
            'principal_amount' => 99999,
            'installment_amount' => 9000,
            'installments_count' => 12,
        ]))->assertSessionHas('error');

        $this->assertEqualsWithDelta(12000, (float) $loan->fresh()->principal_amount, 0.01);
    }

    public function test_a_cash_repayment_is_recorded(): void
    {
        $loan = $this->activeLoan();

        $this->post(route('employee-loans.repay', $loan), [
            'date' => '2026-08-10',
            'amount' => 3000,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(9000, (float) $loan->fresh()->outstanding_amount, 0.01);
    }

    private function loanPayload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'loan_type' => 'loan',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'principal_amount' => 12000,
            'installment_amount' => 1000,
            'installments_count' => 12,
            'deduct_from_payroll' => true,
            'issue_date' => '2026-07-01',
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
        ], $overrides);
    }

    private function activeLoan(): EmployeeLoan
    {
        $loan = EmployeeLoan::create($this->loanPayload() + [
            'number' => (string) EmployeeLoan::nextNumber(),
            'status' => LoanStatus::Draft->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $service = app(EmployeeLoanService::class);

        return $service->disburse($service->approve($loan));
    }

    // ==================================================
    // RECRUITMENT
    // ==================================================

    public function test_a_job_opening_is_created_and_published(): void
    {
        $this->post(route('job-openings.store'), [
            'code' => 'JOB-001',
            'title' => 'Accountant',
            'employment_type' => 'permanent',
            'vacancies' => 2,
            'posted_date' => '2026-08-01',
            'closing_date' => '2026-09-01',
        ])->assertSessionHasNoErrors();

        $opening = JobOpening::firstOrFail();

        $this->patch(route('job-openings.transition', $opening), [
            'status' => JobOpeningStatus::Published->value,
        ])->assertSessionHasNoErrors();

        $this->assertSame(JobOpeningStatus::Published, $opening->fresh()->statusEnum());
    }

    public function test_an_application_to_an_unpublished_opening_is_rejected(): void
    {
        $opening = JobOpening::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'status' => JobOpeningStatus::Draft->value,
        ]);

        $this->post(route('job-applications.store'), [
            'job_opening_id' => $opening->id,
            'application_number' => 'APP-001',
            'full_name' => 'Ahmad Karimi',
            'source' => 'website',
        ])->assertSessionHasErrors('job_opening_id');
    }

    public function test_a_candidate_is_hired_and_becomes_an_employee(): void
    {
        $opening = JobOpening::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'status' => JobOpeningStatus::Published->value,
            'vacancies' => 1,
        ]);

        $application = JobApplication::factory()->create([
            'job_opening_id' => $opening->id,
            'branch_id' => $this->ctx['branch']->id,
            'full_name' => 'Sayed Rahim Noori',
            'status' => ApplicationStatus::Shortlisted->value,
        ]);

        $this->patch(route('job-applications.transition', $application), [
            'status' => ApplicationStatus::Offered->value,
            'offered_salary' => 38000,
        ])->assertSessionHasNoErrors();

        $before = Employee::query()->count();

        $this->post(route('job-applications.hire', $application), [
            'joining_date' => '2026-09-01',
        ])->assertSessionHasNoErrors();

        $this->assertSame($before + 1, Employee::query()->count());
        $this->assertNotNull($application->fresh()->hired_employee_id);
    }

    public function test_an_interview_is_scheduled_and_completed(): void
    {
        $application = JobApplication::factory()->create([
            'job_opening_id' => JobOpening::factory()->create([
                'branch_id' => $this->ctx['branch']->id,
                'status' => JobOpeningStatus::Published->value,
            ])->id,
            'branch_id' => $this->ctx['branch']->id,
            'status' => ApplicationStatus::Shortlisted->value,
        ]);

        $this->post(route('interviews.store'), [
            'job_application_id' => $application->id,
            'interview_type' => 'in_person',
            'scheduled_at' => '2026-08-25 10:00:00',
            'panelists' => [
                ['employee_id' => $this->employee->id, 'is_lead' => true],
            ],
        ])->assertSessionHasNoErrors();

        $interview = \App\Models\Hr\Interview::firstOrFail();
        $panelist = $interview->panelists()->firstOrFail();

        $this->patch(route('interviews.feedback', [
            'interview' => $interview,
            'panelist' => $panelist,
        ]), ['score' => 8, 'recommendation' => 'hire'])->assertSessionHasNoErrors();

        $this->patch(route('interviews.complete', $interview))->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(8, (float) $interview->fresh()->score, 0.01);
    }

    /** A video interview with no link is a meeting nobody can attend. */
    public function test_a_video_interview_without_a_link_is_rejected(): void
    {
        $application = JobApplication::factory()->create([
            'job_opening_id' => JobOpening::factory()->create([
                'branch_id' => $this->ctx['branch']->id,
            ])->id,
            'branch_id' => $this->ctx['branch']->id,
            'status' => ApplicationStatus::Shortlisted->value,
        ]);

        $this->post(route('interviews.store'), [
            'job_application_id' => $application->id,
            'interview_type' => 'video',
            'scheduled_at' => '2026-08-25 10:00:00',
        ])->assertSessionHasErrors('meeting_link');
    }

    // ==================================================
    // FIXTURES
    // ==================================================

    private function payrollPayload(array $overrides = []): array
    {
        return array_merge([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'pay_date' => '2026-08-31',
            'pay_frequency' => 'monthly',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
        ], $overrides);
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

    private function postedPayroll(): Payroll
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

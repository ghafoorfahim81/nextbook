<?php

namespace Tests\Feature\Hr;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\PayrollStatus;
use App\Enums\TaxPeriod;
use App\Models\Hr\Attendance;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeLoan;
use App\Models\Hr\LeaveAllocation;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Payroll;
use App\Models\Hr\SalaryComponent;
use App\Models\Hr\SalaryStructure;
use App\Models\Hr\Shift;
use App\Models\Hr\TaxBracket;
use App\Models\Hr\TaxBracketSet;
use App\Services\Hr\EmployeeLoanService;
use App\Services\Hr\PayrollCalculationService;
use App\Services\Hr\PayrollPostingService;
use App\Services\Hr\SalaryDisbursementService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The HR reports.
 *
 * Every one of them has to return a well-formed payload — the Excel and PDF
 * exporters consume the same shape, so a report that returns a raw collection
 * or forgets its summary breaks both at once and only at download time.
 *
 * Beyond that, the numbers are checked against fixtures with known answers.
 * A report that runs but reports the wrong figure is the more expensive
 * failure of the two.
 */
class HrReportFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Employee $employee;

    private const PERIOD_START = '2026-08-01';

    private const PERIOD_END = '2026-08-31';

    /** Net on a 50,000 basic: 50,000 - (150 + 10% of 37,500) = 46,100. */
    private const NET = 46100.0;

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
            'tin' => '1234567',
        ]);
    }

    private function report(string $key, array $overrides = []): array
    {
        return app(ReportService::class)->getReportData($this->filters($key, $overrides));
    }

    private function filters(string $key, array $overrides = []): array
    {
        return array_merge([
            'report' => $key,
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => self::PERIOD_START,
            'date_to' => self::PERIOD_END,
            'ledger_id' => null, 'customer_id' => null, 'supplier_id' => null,
            'item_id' => null, 'account_id' => null, 'currency_id' => null,
            'warehouse_id' => null, 'type' => null, 'balance_type' => 'all',
            'category_id' => null, 'expense_account_id' => null,
            'employee_id' => null, 'department_id' => null, 'designation_id' => null,
            'payroll_id' => null, 'leave_type_id' => null,
            'employment_status' => null, 'employment_type' => null,
            'view_type' => 'itemwise', 'per_page' => 25, 'page' => 1,
        ], $overrides);
    }

    /**
     * The contract the Excel and PDF exporters both rely on.
     */
    private function assertValidPayload(array $result): void
    {
        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertIsArray($result['rows']);
        $this->assertArrayHasKey('total', $result['pagination']);

        foreach ($result['rows'] as $row) {
            $this->assertIsArray($row, 'Every row must be a plain array the exporter can key into.');
        }
    }

    // ==================================================
    // SHAPE
    // ==================================================

    /**
     * Run every HR report against an empty branch. An empty result still has
     * to be a valid payload — a report nobody has data for yet is the FIRST
     * one a new customer opens.
     */
    public function test_every_hr_report_returns_a_valid_payload_when_empty(): void
    {
        foreach ($this->hrReportKeys() as $key) {
            $result = $this->report($key);

            $this->assertValidPayload($result);

            // headcount_report is excepted: setUp() hires one employee, and an
            // employee with no payroll, no attendance and no leave is exactly
            // what a new customer's first day looks like. Headcount SHOULD
            // report them — that is the report working, not leaking.
            if ($key !== 'headcount_report') {
                $this->assertSame([], $result['rows'], "{$key} should be empty here.");
            }
        }

        $this->assertSame(1, $this->report('headcount_report')['summary']['headcount']);
    }

    public function test_every_hr_report_returns_a_valid_payload_with_data(): void
    {
        $this->seedEverything();

        foreach ($this->hrReportKeys() as $key) {
            $this->assertValidPayload($this->report($key));
        }
    }

    /**
     * Every key must be dispatched. A key in REPORT_KEYS with no arm in the
     * match falls through to the trial balance, and the user gets a completely
     * different report with no error at all.
     */
    public function test_no_hr_report_key_falls_through_to_the_trial_balance(): void
    {
        $this->seedEverything();

        $trialBalance = $this->report('trial_balance');

        foreach ($this->hrReportKeys() as $key) {
            $this->assertNotSame(
                $trialBalance,
                $this->report($key),
                "{$key} is not dispatched — it silently returns the trial balance."
            );
        }
    }

    /** @return array<int, string> */
    private function hrReportKeys(): array
    {
        return [
            'payroll_register', 'payslip_summary', 'tax_withholding_report',
            'employee_loan_statement', 'attendance_summary', 'attendance_register',
            'leave_balance_report', 'leave_register', 'headcount_report',
            'contract_expiry_report',
        ];
    }

    // ==================================================
    // PAYROLL
    // ==================================================

    public function test_the_payroll_register_reports_the_payslip_figures(): void
    {
        $this->seedPayroll();

        $result = $this->report('payroll_register');

        $this->assertCount(1, $result['rows']);
        $row = $result['rows'][0];

        $this->assertSame($this->employee->code, $row['employee_code']);
        $this->assertEqualsWithDelta(50000, $row['gross_earnings'], 0.01);
        $this->assertEqualsWithDelta(3900, $row['tax_amount'], 0.01);
        $this->assertEqualsWithDelta(self::NET, $row['net_payable'], 0.01);
        $this->assertSame('1405-05', $row['period']);

        $this->assertEqualsWithDelta(self::NET, $result['summary']['base_net'], 0.01);
        $this->assertEqualsWithDelta(self::NET, $result['summary']['base_outstanding'], 0.01);
    }

    /**
     * A reversed run is not what anybody was paid. Including it would double
     * the register for any period that was ever corrected.
     */
    public function test_a_reversed_payroll_leaves_the_register(): void
    {
        $payroll = $this->seedPayroll();

        $this->assertCount(1, $this->report('payroll_register')['rows']);

        app(PayrollPostingService::class)->reverse($payroll, 'correction');

        $this->assertCount(0, $this->report('payroll_register')['rows']);
    }

    public function test_an_uncalculated_run_is_not_in_the_register(): void
    {
        $this->seedPayrollFixtures();
        app(PayrollCalculationService::class)->calculate($this->draftPayroll());

        // Calculated, never posted — nobody has been paid anything.
        $this->assertCount(0, $this->report('payroll_register')['rows']);
    }

    public function test_paying_a_payslip_shows_up_in_the_register(): void
    {
        $this->seedPayroll();

        app(SalaryDisbursementService::class)->pay([
            'employee_id' => $this->employee->id,
            'date' => self::PERIOD_END,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'amount' => 20000,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
        ]);

        $result = $this->report('payroll_register');

        $this->assertEqualsWithDelta(20000, $result['rows'][0]['paid_amount'], 0.01);
        $this->assertSame('partial', $result['rows'][0]['payment_status']);
        $this->assertEqualsWithDelta(
            self::NET - 20000,
            $result['summary']['base_outstanding'],
            0.01
        );
    }

    public function test_the_payslip_summary_rolls_the_run_up(): void
    {
        $this->seedPayroll();

        $result = $this->report('payslip_summary');

        $this->assertCount(1, $result['rows']);
        $this->assertSame(1, $result['rows'][0]['employee_count']);
        $this->assertEqualsWithDelta(50000, $result['rows'][0]['total_gross'], 0.01);
        $this->assertEqualsWithDelta(self::NET, $result['rows'][0]['outstanding'], 0.01);
    }

    public function test_the_tax_report_names_the_table_each_payslip_used(): void
    {
        $this->seedPayroll();

        $result = $this->report('tax_withholding_report');
        $row = $result['rows'][0];

        $this->assertSame('1234567', $row['tin']);
        $this->assertSame('AF Monthly', $row['tax_table_name']);
        $this->assertEqualsWithDelta(3900, $row['tax_amount'], 0.01);
        $this->assertEqualsWithDelta(3900, $result['summary']['base_tax'], 0.01);
    }

    public function test_an_exempt_employee_appears_with_zero_tax(): void
    {
        $this->employee->update(['is_tax_exempt' => true]);
        $this->seedPayroll();

        $row = $this->report('tax_withholding_report')['rows'][0];

        $this->assertTrue($row['is_tax_exempt']);
        $this->assertEqualsWithDelta(0, $row['tax_amount'], 0.01);
    }

    // ==================================================
    // LOANS
    // ==================================================

    public function test_the_loan_statement_shows_repaid_and_outstanding(): void
    {
        $loan = $this->seedLoan();

        app(EmployeeLoanService::class)->repayInCash($loan, [
            'date' => '2026-08-10',
            'amount' => 3000,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
        ]);

        $result = $this->report('employee_loan_statement');
        $row = $result['rows'][0];

        $this->assertEqualsWithDelta(12000, $row['principal_amount'], 0.01);
        $this->assertEqualsWithDelta(3000, $row['repaid_amount'], 0.01);
        $this->assertEqualsWithDelta(9000, $row['outstanding_amount'], 0.01);
        $this->assertEqualsWithDelta(9000, $result['summary']['base_outstanding'], 0.01);
    }

    // ==================================================
    // ATTENDANCE
    // ==================================================

    public function test_the_attendance_summary_counts_each_status_once(): void
    {
        $this->seedAttendance();

        $result = $this->report('attendance_summary');
        $row = $result['rows'][0];

        $this->assertSame(3, $row['present_days']);
        $this->assertSame(1, $row['late_days']);
        $this->assertSame(2, $row['absent_days']);
        $this->assertSame(1, $row['leave_days']);
        $this->assertEqualsWithDelta(25.5, $row['worked_hours'], 0.01);
    }

    public function test_the_attendance_register_lists_the_days(): void
    {
        $this->seedAttendance();

        $result = $this->report('attendance_register');

        $this->assertCount(7, $result['rows']);
        $this->assertSame(7, $result['summary']['day_count']);
    }

    public function test_the_attendance_register_can_be_filtered_to_one_status(): void
    {
        $this->seedAttendance();

        $result = $this->report('attendance_register', [
            'type' => AttendanceStatus::Absent->value,
        ]);

        $this->assertCount(2, $result['rows']);
    }

    // ==================================================
    // LEAVE
    // ==================================================

    public function test_the_leave_balance_derives_available_days(): void
    {
        $this->seedLeave();

        $result = $this->report('leave_balance_report');
        $row = $result['rows'][0];

        // 20 entitled + 2 carried − 5 approved = 17. The 3 pending days are
        // reported but NOT subtracted: nobody has agreed to them yet.
        $this->assertEqualsWithDelta(20, $row['entitled_days'], 0.01);
        $this->assertEqualsWithDelta(2, $row['carried_forward_days'], 0.01);
        $this->assertEqualsWithDelta(5, $row['taken_days'], 0.01);
        $this->assertEqualsWithDelta(3, $row['pending_days'], 0.01);
        $this->assertEqualsWithDelta(17, $row['available_days'], 0.01);
    }

    /**
     * A request running from before the window into it is part of that
     * period's leave; filtering on from_date alone would silently drop it.
     */
    public function test_the_leave_register_includes_a_request_overlapping_the_window(): void
    {
        $type = $this->leaveType();

        LeaveRequest::create([
            'number' => (string) LeaveRequest::nextNumber(),
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'from_date' => '2026-07-28',
            'to_date' => '2026-08-03',
            'days' => 7,
            'status' => LeaveRequestStatus::Approved->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $result = $this->report('leave_register');

        $this->assertCount(1, $result['rows']);
        $this->assertEqualsWithDelta(7, $result['summary']['approved_days'], 0.01);
    }

    // ==================================================
    // HEADCOUNT AND CONTRACTS
    // ==================================================

    /**
     * Headcount is a question about a MOMENT. Someone who left mid-period is
     * a leaver, not half a head.
     */
    public function test_headcount_counts_as_at_the_end_of_the_window(): void
    {
        Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'joining_date' => '2026-08-05',
        ]);

        Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'joining_date' => '2023-01-01',
            'separation_date' => '2026-08-20',
        ]);

        $summary = $this->report('headcount_report')['summary'];

        // The original employee plus the joiner; the leaver is gone by the 31st.
        $this->assertSame(2, $summary['headcount']);
        $this->assertSame(1, $summary['joiners']);
        $this->assertSame(1, $summary['leavers']);
        $this->assertSame(0, $summary['net_change']);
    }

    public function test_someone_hired_after_the_window_is_not_counted_yet(): void
    {
        Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'joining_date' => '2026-12-01',
        ]);

        $this->assertSame(1, $this->report('headcount_report')['summary']['headcount']);
    }

    public function test_the_contract_report_lists_contracts_ending_in_the_window(): void
    {
        \App\Models\Hr\EmployeeContract::factory()->create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->ctx['branch']->id,
            'start_date' => '2025-08-15',
            'end_date' => '2026-08-15',
        ]);

        \App\Models\Hr\EmployeeContract::factory()->create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->ctx['branch']->id,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
        ]);

        $result = $this->report('contract_expiry_report');

        $this->assertCount(1, $result['rows']);
        $this->assertSame(1, $result['summary']['contract_count']);
    }

    // ==================================================
    // SCOPING
    // ==================================================

    public function test_reports_can_be_narrowed_to_one_employee(): void
    {
        $this->seedAttendance();

        $other = Employee::factory()->create(['branch_id' => $this->ctx['branch']->id]);
        Attendance::create([
            'employee_id' => $other->id,
            'date' => '2026-08-03',
            'status' => AttendanceStatus::Present->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $this->assertCount(2, $this->report('attendance_summary')['rows']);
        $this->assertCount(
            1,
            $this->report('attendance_summary', ['employee_id' => $other->id])['rows']
        );
    }

    /**
     * Another branch's payroll must never appear. These reports go straight to
     * DB::table() and so bypass the BranchSpecific global scope entirely — the
     * branch_id predicate is the only thing keeping them separate.
     */
    public function test_another_branch_is_not_visible(): void
    {
        $this->seedPayroll();

        $otherBranch = $this->bootstrapErpContext();

        $result = app(ReportService::class)->getReportData(
            $this->filters('payroll_register', ['branch_id' => $otherBranch['branch']->id])
        );

        $this->assertCount(0, $result['rows']);
    }

    // ==================================================
    // FIXTURES
    // ==================================================

    private function seedEverything(): void
    {
        $this->seedPayroll();
        $this->seedLoan();
        $this->seedAttendance();
        $this->seedLeave();

        \App\Models\Hr\EmployeeContract::factory()->create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->ctx['branch']->id,
            'start_date' => '2025-08-15',
            'end_date' => '2026-08-15',
        ]);
    }

    private function seedPayrollFixtures(): void
    {
        if (TaxBracketSet::query()->exists()) {
            return;
        }

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

    private function draftPayroll(): Payroll
    {
        return Payroll::create([
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
    }

    private function seedPayroll(): Payroll
    {
        $this->seedPayrollFixtures();

        $payroll = app(PayrollCalculationService::class)->calculate($this->draftPayroll());

        $service = app(PayrollPostingService::class);
        $payroll = $service->transitionTo($payroll, PayrollStatus::PendingApproval);
        $payroll = $service->transitionTo($payroll, PayrollStatus::Approved);

        return $service->post($payroll);
    }

    private function seedLoan(): EmployeeLoan
    {
        $loan = EmployeeLoan::create([
            'number' => (string) EmployeeLoan::nextNumber(),
            'employee_id' => $this->employee->id,
            'loan_type' => 'loan',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'principal_amount' => 12000,
            'installment_amount' => 1000,
            'installments_count' => 12,
            'deduct_from_payroll' => false,
            'issue_date' => '2026-08-02',
            'status' => 'draft',
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $service = app(EmployeeLoanService::class);

        return $service->disburse($service->approve($loan));
    }

    /** 3 present, 1 late, 2 absent, 1 on leave — 25.5 worked hours. */
    private function seedAttendance(): void
    {
        $days = [
            ['2026-08-02', AttendanceStatus::Present, 8],
            ['2026-08-03', AttendanceStatus::Present, 8],
            ['2026-08-04', AttendanceStatus::Present, 8],
            ['2026-08-05', AttendanceStatus::Late, 1.5],
            ['2026-08-06', AttendanceStatus::Absent, 0],
            ['2026-08-09', AttendanceStatus::Absent, 0],
            ['2026-08-10', AttendanceStatus::OnLeave, 0],
        ];

        foreach ($days as [$date, $status, $hours]) {
            Attendance::create([
                'employee_id' => $this->employee->id,
                'date' => $date,
                'status' => $status->value,
                'worked_hours' => $hours,
                'branch_id' => $this->ctx['branch']->id,
                'created_by' => $this->ctx['user']->id,
            ]);
        }
    }

    private function leaveType(): LeaveType
    {
        return LeaveType::query()->first() ?: LeaveType::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Annual leave',
            'days_per_year' => 20,
            'is_paid' => true,
        ]);
    }

    private function seedLeave(): void
    {
        $type = $this->leaveType();

        LeaveAllocation::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'period_start' => '2026-03-21',
            'period_end' => '2027-03-20',
            'entitled_days' => 20,
            'carried_forward_days' => 2,
            'adjustment_days' => 0,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        LeaveRequest::create([
            'number' => (string) LeaveRequest::nextNumber(),
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'from_date' => '2026-08-10',
            'to_date' => '2026-08-14',
            'days' => 5,
            'status' => LeaveRequestStatus::Approved->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        LeaveRequest::create([
            'number' => (string) LeaveRequest::nextNumber(),
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'from_date' => '2026-08-24',
            'to_date' => '2026-08-26',
            'days' => 3,
            'status' => LeaveRequestStatus::Pending->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);
    }
}

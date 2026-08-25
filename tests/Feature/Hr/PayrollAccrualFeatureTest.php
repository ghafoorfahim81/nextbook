<?php

namespace Tests\Feature\Hr;

use App\Enums\ComponentCalculationType;
use App\Enums\PayrollStatus;
use App\Enums\SalaryComponentType;
use App\Enums\TaxPeriod;
use App\Exceptions\Hr\PayrollException;
use App\Models\Hr\Employee;
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
use App\Support\Decimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Payroll calculation and its posting to the general ledger.
 *
 * The invariants worth pinning are the ones that cost real money when wrong:
 * the voucher must balance in every currency, the liability must be per
 * employee (or the salary can never be paid), and tax must come from the
 * bracket set the payslip recorded.
 */
class PayrollAccrualFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Employee $employee;

    private TaxBracketSet $taxSet;

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
            'employment_type' => 'permanent',
            'joining_date' => '2024-01-01',
        ]);

        $this->taxSet = $this->seedTaxSet();
        $this->seedSystemComponents();
    }

    private function seedTaxSet(): TaxBracketSet
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

        return $set->fresh();
    }

    private function seedSystemComponents(): void
    {
        foreach (SalaryComponent::defaultComponents() as $component) {
            SalaryComponent::create(array_merge(['affects_gross' => true, 'is_active' => true], $component, [
                'branch_id' => $this->ctx['branch']->id,
                'created_by' => $this->ctx['user']->id,
            ]));
        }
    }

    private function structureFor(Employee $employee, float $basic = 50000): SalaryStructure
    {
        return SalaryStructure::create([
            'name' => 'Package',
            'employee_id' => $employee->id,
            'currency_id' => $employee->currency_id,
            'effective_from' => '2024-01-01',
            'basic_salary' => $basic,
            'pay_frequency' => 'monthly',
            'is_active' => true,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);
    }

    private function payroll(): Payroll
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

    private function calculate(): Payroll
    {
        return app(PayrollCalculationService::class)->calculate($this->payroll());
    }

    /**
     * Walk a calculated run through submit and approve, then post it.
     *
     * Posting straight from `calculated` is refused by the state machine on
     * purpose — approving payroll is a separate authority from preparing it.
     */
    private function postPayroll(?Payroll $payroll = null): Payroll
    {
        $payroll = $payroll ?? $this->calculate();
        $service = app(PayrollPostingService::class);

        $payroll = $service->transitionTo($payroll, PayrollStatus::PendingApproval);
        $payroll = $service->transitionTo($payroll, PayrollStatus::Approved);

        return $service->post($payroll);
    }

    public function test_it_calculates_basic_pay_and_tax(): void
    {
        $this->structureFor($this->employee, 50000);

        $payroll = $this->calculate();
        $line = $payroll->lines()->firstOrFail();

        $this->assertEqualsWithDelta(50000, (float) $line->gross_earnings, 0.01);
        // 150 + 10% of (50,000 - 12,500) = 3,900.
        $this->assertEqualsWithDelta(3900, (float) $line->tax_amount, 0.01);
        $this->assertEqualsWithDelta(46100, (float) $line->net_payable, 0.01);
        $this->assertSame(PayrollStatus::Calculated, $payroll->statusEnum());
    }

    public function test_the_payslip_records_which_tax_table_it_used(): void
    {
        $this->structureFor($this->employee);

        $line = $this->calculate()->lines()->firstOrFail();

        $this->assertSame($this->taxSet->id, $line->tax_bracket_set_id);
    }

    /**
     * An exempt employee still gets a zero tax line, so the payslip shows the
     * exemption rather than silently omitting it.
     */
    public function test_a_tax_exempt_employee_gets_an_explicit_zero_line(): void
    {
        $this->employee->update(['is_tax_exempt' => true]);
        $this->structureFor($this->employee, 50000);

        $line = $this->calculate()->lines()->firstOrFail();

        $this->assertEqualsWithDelta(0, (float) $line->tax_amount, 0.01);

        $taxLine = $line->components->firstWhere('component_code', SalaryComponent::CODE_WAGE_TAX);
        $this->assertNotNull($taxLine, 'The exemption should still appear as a line.');
        $this->assertEqualsWithDelta(0, (float) $taxLine->amount, 0.01);
    }

    public function test_a_percent_of_basic_allowance_is_added_to_gross(): void
    {
        $structure = $this->structureFor($this->employee, 40000);

        $allowance = SalaryComponent::create([
            'name' => 'Transport', 'code' => 'TRANSPORT',
            'component_type' => SalaryComponentType::Earning->value,
            'calculation_type' => ComponentCalculationType::PercentOfBasic->value,
            'percentage' => 10, 'is_taxable' => true, 'is_active' => true,
            'branch_id' => $this->ctx['branch']->id, 'created_by' => $this->ctx['user']->id,
        ]);

        SalaryStructureLine::create([
            'salary_structure_id' => $structure->id,
            'salary_component_id' => $allowance->id,
            'calculation_type' => ComponentCalculationType::PercentOfBasic->value,
            'percentage' => 10, 'sequence' => 10,
            'branch_id' => $this->ctx['branch']->id, 'created_by' => $this->ctx['user']->id,
        ]);

        $line = $this->calculate()->lines()->firstOrFail();

        $this->assertEqualsWithDelta(44000, (float) $line->gross_earnings, 0.01);
    }

    /**
     * Percent-of-gross has to resolve after every other earning, or the gross
     * it references is incomplete.
     */
    public function test_percent_of_gross_resolves_after_other_earnings(): void
    {
        $structure = $this->structureFor($this->employee, 40000);

        $fixed = SalaryComponent::create([
            'name' => 'Housing', 'code' => 'HOUSING',
            'component_type' => SalaryComponentType::Earning->value,
            'calculation_type' => ComponentCalculationType::Fixed->value,
            'amount' => 10000, 'is_taxable' => true, 'is_active' => true,
            'branch_id' => $this->ctx['branch']->id, 'created_by' => $this->ctx['user']->id,
        ]);

        $bonus = SalaryComponent::create([
            'name' => 'Bonus', 'code' => 'BONUS',
            'component_type' => SalaryComponentType::Earning->value,
            'calculation_type' => ComponentCalculationType::PercentOfGross->value,
            'percentage' => 10, 'is_taxable' => true, 'is_active' => true,
            'branch_id' => $this->ctx['branch']->id, 'created_by' => $this->ctx['user']->id,
        ]);

        foreach ([[$fixed, ComponentCalculationType::Fixed, 10000, null, 10],
                  [$bonus, ComponentCalculationType::PercentOfGross, null, 10, 20]] as [$c, $calc, $amt, $pct, $seq]) {
            SalaryStructureLine::create([
                'salary_structure_id' => $structure->id,
                'salary_component_id' => $c->id,
                'calculation_type' => $calc->value,
                'amount' => $amt, 'percentage' => $pct, 'sequence' => $seq,
                'branch_id' => $this->ctx['branch']->id, 'created_by' => $this->ctx['user']->id,
            ]);
        }

        $line = $this->calculate()->lines()->firstOrFail();

        // 10% of (40,000 + 10,000) = 5,000, not 10% of basic alone.
        $bonusLine = $line->components->firstWhere('component_code', 'BONUS');
        $this->assertEqualsWithDelta(5000, (float) $bonusLine->amount, 0.01);
        $this->assertEqualsWithDelta(55000, (float) $line->gross_earnings, 0.01);
    }

    /**
     * The whole point of the accrual shape: each employee's net must be its own
     * GL line carrying their ledger, or the salary can never be settled.
     */
    public function test_the_accrual_credits_each_employee_individually(): void
    {
        $this->structureFor($this->employee, 50000);

        $second = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'joining_date' => '2024-01-01',
        ]);
        $this->structureFor($second, 30000);

        $payroll = $this->postPayroll();

        $payableId = $this->ctx['accounts']['payroll-liabilities']->id;

        $partyLines = TransactionLine::withoutGlobalScopes()
            ->where('transaction_id', $payroll->transaction_id)
            ->where('account_id', $payableId)
            ->get();

        $this->assertCount(2, $partyLines, 'One payable line per employee.');
        $this->assertTrue(
            $partyLines->every(fn (TransactionLine $l) => $l->ledger_id !== null),
            'Every payable line must carry the employee ledger, or settlement finds nothing.'
        );

        $ledgerIds = $partyLines->pluck('ledger_id')->all();
        $this->assertContains($this->employee->fresh()->ledger_id, $ledgerIds);
        $this->assertContains($second->fresh()->ledger_id, $ledgerIds);
    }

    public function test_the_accrual_voucher_balances(): void
    {
        $this->structureFor($this->employee, 50000);

        $payroll = $this->postPayroll();

        $lines = TransactionLine::withoutGlobalScopes()
            ->where('transaction_id', $payroll->transaction_id)
            ->get();

        $debit = $lines->sum(fn (TransactionLine $l) => (float) $l->base_debit);
        $credit = $lines->sum(fn (TransactionLine $l) => (float) $l->base_credit);

        $this->assertEqualsWithDelta($debit, $credit, 0.0001);
        $this->assertGreaterThan(0, $debit);
    }

    public function test_withheld_tax_credits_the_liability_not_an_expense(): void
    {
        $this->structureFor($this->employee, 50000);

        $payroll = $this->postPayroll();

        $taxPayable = $this->ctx['accounts']['salary-tax-payable']->id;

        $taxLine = TransactionLine::withoutGlobalScopes()
            ->where('transaction_id', $payroll->transaction_id)
            ->where('account_id', $taxPayable)
            ->first();

        $this->assertNotNull($taxLine, 'Wage tax must credit the payable, never an expense account.');
        $this->assertEqualsWithDelta(3900, (float) $taxLine->credit, 0.01);
    }

    public function test_permanent_staff_salary_hits_the_permanent_expense_account(): void
    {
        $this->structureFor($this->employee, 50000);

        $payroll = $this->postPayroll();

        $expected = $this->ctx['accounts']['permanent-staff-salary']->id;

        $this->assertTrue(
            TransactionLine::withoutGlobalScopes()
                ->where('transaction_id', $payroll->transaction_id)
                ->where('account_id', $expected)
                ->where('debit', '>', 0)
                ->exists()
        );
    }

    public function test_a_consultant_hits_the_consultant_expense_account(): void
    {
        $this->employee->update(['employment_type' => 'consultant']);
        $this->structureFor($this->employee, 50000);

        $payroll = $this->postPayroll();

        $expected = $this->ctx['accounts']['consultant-professional-salary']->id;

        $this->assertTrue(
            TransactionLine::withoutGlobalScopes()
                ->where('transaction_id', $payroll->transaction_id)
                ->where('account_id', $expected)
                ->where('debit', '>', 0)
                ->exists()
        );
    }

    public function test_posting_locks_the_attendance_it_used(): void
    {
        $this->structureFor($this->employee, 50000);

        \App\Models\Hr\Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-08-17',
            'status' => 'present',
            'worked_hours' => 8,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $payroll = $this->postPayroll();

        $this->assertSame(1, \App\Models\Hr\Attendance::query()->where('payroll_id', $payroll->id)->count());
    }

    public function test_a_posted_payroll_cannot_be_recalculated(): void
    {
        $this->structureFor($this->employee, 50000);

        $payroll = $this->postPayroll();

        $this->expectException(PayrollException::class);
        app(PayrollCalculationService::class)->calculate($payroll);
    }

    public function test_recalculating_replaces_lines_rather_than_accumulating(): void
    {
        $this->structureFor($this->employee, 50000);

        $payroll = $this->calculate();
        $firstCount = $payroll->lines()->count();

        $payroll = app(PayrollCalculationService::class)->calculate($payroll);

        $this->assertSame($firstCount, $payroll->lines()->count());
        $this->assertSame(1, $payroll->lines()->count());
    }

    public function test_an_employee_who_joined_after_the_period_is_excluded(): void
    {
        $this->structureFor($this->employee, 50000);

        $newcomer = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'joining_date' => '2026-09-15',
        ]);
        $this->structureFor($newcomer, 30000);

        $payroll = $this->calculate();

        $this->assertSame(1, $payroll->lines()->count());
        $this->assertSame(
            $this->employee->id,
            $payroll->lines()->firstOrFail()->employee_id
        );
    }

    public function test_a_run_with_no_eligible_employees_throws(): void
    {
        Employee::query()->delete();

        $this->expectException(PayrollException::class);
        $this->calculate();
    }

    public function test_the_run_totals_match_the_sum_of_its_payslips(): void
    {
        $this->structureFor($this->employee, 50000);

        $second = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'joining_date' => '2024-01-01',
        ]);
        $this->structureFor($second, 20000);

        $payroll = $this->calculate();

        $this->assertEqualsWithDelta(
            $payroll->lines()->sum('base_gross'),
            (float) $payroll->total_gross,
            0.01
        );
        $this->assertEqualsWithDelta(
            $payroll->lines()->sum('base_net'),
            (float) $payroll->total_net,
            0.01
        );
        $this->assertSame(2, $payroll->employee_count);
    }
}

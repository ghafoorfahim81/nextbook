<?php

namespace App\Services\Hr;

use App\Enums\AttendanceStatus;
use App\Enums\ComponentCalculationType;
use App\Enums\PayrollLinePaymentStatus;
use App\Enums\PayrollStatus;
use App\Enums\SalaryComponentType;
use App\Enums\TaxPeriod;
use App\Exceptions\Hr\PayrollException;
use App\Models\Hr\Attendance;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeLoan;
use App\Models\Hr\Payroll;
use App\Models\Hr\PayrollLine;
use App\Models\Hr\PayrollLineComponent;
use App\Models\Hr\SalaryComponent;
use App\Models\Hr\SalaryStructure;
use App\Models\Hr\Shift;
use App\Models\Hr\TaxBracketSet;
use App\Support\BranchContext;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the payslips for a run.
 *
 * Order matters and is deliberate:
 *
 *   1. Basic salary, prorated by unpaid leave.
 *   2. Fixed and percent-of-basic earnings.
 *   3. Overtime.
 *   4. Percent-of-GROSS earnings — a second pass, because the gross they
 *      reference is not complete until every other earning is resolved.
 *   5. Deductions, then loan recovery.
 *   6. Tax on taxable earnings only, using the bracket set in force.
 *
 * Every amount is a decimal string end to end. A payslip computed in floats and
 * a ledger line computed in decimals will eventually disagree by a hundredth,
 * and TransactionService rejects entries that do not balance.
 */
class PayrollCalculationService
{
    public function __construct(
        private readonly WageTaxService $wageTax,
    ) {
    }

    /**
     * Rebuild every payslip on a run.
     *
     * Destructive by design: lines and their components are deleted and
     * recreated, so a recalculation after an attendance correction produces the
     * same result as a first run rather than accumulating adjustments.
     */
    public function calculate(Payroll $payroll): Payroll
    {
        if (! $payroll->isRecalculable()) {
            throw PayrollException::make(
                'This payroll has been posted and can no longer be recalculated. Reverse it and run a new one.',
                ['payroll_id' => $payroll->id, 'status' => $payroll->statusEnum()->value]
            );
        }

        return DB::transaction(function () use ($payroll) {
            $employees = $this->eligibleEmployees($payroll);

            if ($employees->isEmpty()) {
                throw PayrollException::make(
                    'No employees match this payroll run.',
                    ['payroll_id' => $payroll->id]
                );
            }

            $this->assertNobodyIsAlreadyPaid($payroll, $employees);

            $taxSet = $this->wageTax->resolveSet(
                $payroll->period_end,
                TaxPeriod::Monthly,
                $payroll->branch_id
            );

            $systemComponents = $this->systemComponents($payroll->branch_id);

            // Force-delete rather than soft-delete: a recalculated run should
            // leave one set of lines behind, not a growing pile of trashed ones
            // that every report then has to filter out.
            $payroll->lines()->each(function (PayrollLine $line) {
                $line->components()->forceDelete();
                $line->forceDelete();
            });

            $totals = ['gross' => '0.0000', 'deductions' => '0.0000', 'tax' => '0.0000', 'net' => '0.0000'];

            foreach ($employees as $employee) {
                $line = $this->buildLine($payroll, $employee, $taxSet, $systemComponents);

                if (! $line) {
                    continue;
                }

                $totals['gross'] = Decimal::add($totals['gross'], Decimal::amount($line->base_gross));
                $totals['deductions'] = Decimal::add($totals['deductions'], Decimal::amount($line->total_deductions));
                $totals['tax'] = Decimal::add($totals['tax'], Decimal::amount($line->tax_amount));
                $totals['net'] = Decimal::add($totals['net'], Decimal::amount($line->base_net));
            }

            $payroll->forceFill([
                'total_gross' => $totals['gross'],
                'total_deductions' => $totals['deductions'],
                'total_tax' => $totals['tax'],
                'total_net' => $totals['net'],
                'employee_count' => $payroll->lines()->count(),
                'status' => \App\Enums\PayrollStatus::Calculated->value,
            ])->save();

            return $payroll->fresh();
        });
    }

    /**
     * @return Collection<int, Employee>
     */
    private function eligibleEmployees(Payroll $payroll): Collection
    {
        return Employee::query()
            ->employed()
            ->where('is_active', true)
            // Anyone who joined after the period ended, or left before it
            // began, was not employed during it.
            ->whereDate('joining_date', '<=', $payroll->period_end->toDateString())
            ->where(function ($q) use ($payroll) {
                $q->whereNull('separation_date')
                    ->orWhereDate('separation_date', '>=', $payroll->period_start->toDateString());
            })
            ->when($payroll->department_id, fn ($q) => $q->where('department_id', $payroll->department_id))
            ->when($payroll->employment_type, fn ($q) => $q->where('employment_type', $payroll->employment_type))
            ->with(['shift', 'currency'])
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Refuse the run if any of its employees already has a live payslip
     * covering an overlapping period.
     *
     * The FormRequest compares SCOPES, which is a good early warning but not
     * authoritative: an employee who changed department between two runs, or a
     * company-wide run raised alongside a departmental one, both slip past a
     * scope comparison. This checks the thing that actually matters — the same
     * person being paid twice for the same days — and it runs inside the
     * calculation transaction, so two concurrent runs cannot both pass it.
     *
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     */
    private function assertNobodyIsAlreadyPaid(Payroll $payroll, $employees): void
    {
        $clash = PayrollLine::query()
            ->join('payrolls', 'payrolls.id', '=', 'payroll_lines.payroll_id')
            ->whereIn('payroll_lines.employee_id', $employees->pluck('id'))
            ->where('payrolls.id', '!=', $payroll->id)
            ->whereNull('payrolls.deleted_at')
            ->whereNull('payroll_lines.deleted_at')
            ->whereNotIn('payrolls.status', [
                PayrollStatus::Reversed->value,
                PayrollStatus::Cancelled->value,
            ])
            ->where('payrolls.period_start', '<=', $payroll->period_end->toDateString())
            ->where('payrolls.period_end', '>=', $payroll->period_start->toDateString())
            // Lock the clashing rows so a concurrent run blocks here rather
            // than reading a stale absence and paying the same person twice.
            ->lockForUpdate()
            ->select('payroll_lines.employee_id', 'payrolls.number')
            ->first();

        if ($clash) {
            throw PayrollException::make(
                'Some of these employees are already on another payroll covering this period.',
                [
                    'payroll_id' => $payroll->id,
                    'conflicting_payroll' => $clash->number,
                    'employee_id' => $clash->employee_id,
                ]
            );
        }
    }

    /**
     * The share of the period an employee was actually employed for.
     *
     * Returns 1 for anyone employed throughout. A joiner or a leaver gets the
     * fraction of the period's working days that fall inside their employment,
     * so someone who started on the 25th is not paid a full month.
     *
     * @return array{fraction: string, payable_days: string}
     */
    private function employmentShare(Payroll $payroll, Employee $employee, string $workingDays): array
    {
        $periodStart = $payroll->period_start->copy();
        $periodEnd = $payroll->period_end->copy();

        $joined = $employee->joining_date?->copy();
        $left = $employee->separation_date?->copy();

        $activeFrom = $joined && $joined->greaterThan($periodStart) ? $joined : $periodStart;
        $activeTo = $left && $left->lessThan($periodEnd) ? $left : $periodEnd;

        // Employed for the whole period — nothing to prorate.
        if ($activeFrom->lessThanOrEqualTo($periodStart) && $activeTo->greaterThanOrEqualTo($periodEnd)) {
            return ['fraction' => '1.0000', 'payable_days' => $workingDays];
        }

        $shift = $employee->shift;

        // Shift::worksOn() is the one place that knows how working_days is
        // stored, including that the values may come back from JSON as
        // strings. Re-implementing the weekday test here silently matched
        // nothing, so every partial period counted zero working days and fell
        // through to the "pay in full" fallback below.
        $isWorkingDay = fn ($date): bool => $shift
            ? $shift->worksOn($date)
            : (int) $date->isoWeekday() !== 5;   // Friday off by default

        $countWorkingDays = function ($from, $to) use ($isWorkingDay): int {
            $days = 0;
            $cursor = $from->copy();

            while ($cursor->lessThanOrEqualTo($to)) {
                if ($isWorkingDay($cursor)) {
                    $days++;
                }
                $cursor->addDay();
            }

            return $days;
        };

        $periodDays = $countWorkingDays($periodStart, $periodEnd);
        $activeDays = $activeFrom->greaterThan($activeTo) ? 0 : $countWorkingDays($activeFrom, $activeTo);

        // A shift with no working days at all cannot produce a ratio. Fall back
        // to calendar days rather than paying a partial period in full.
        if ($periodDays <= 0) {
            $periodDays = $periodStart->diffInDays($periodEnd) + 1;
            $activeDays = $activeFrom->greaterThan($activeTo)
                ? 0
                : $activeFrom->diffInDays($activeTo) + 1;
        }

        if ($periodDays <= 0) {
            return ['fraction' => '1.0000', 'payable_days' => $workingDays];
        }

        return [
            'fraction' => bcdiv((string) $activeDays, (string) $periodDays, Decimal::AMOUNT_SCALE),
            'payable_days' => (string) $activeDays,
        ];
    }

    /**
     * @return array<string, SalaryComponent>
     */
    private function systemComponents(string $branchId): array
    {
        return SalaryComponent::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->where('is_system', true)
            ->get()
            ->keyBy('code')
            ->all();
    }

    /**
     * @param  array<string, SalaryComponent>  $systemComponents
     */
    private function buildLine(
        Payroll $payroll,
        Employee $employee,
        TaxBracketSet $taxSet,
        array $systemComponents,
    ): ?PayrollLine {
        $structure = SalaryStructure::resolveFor($employee, $payroll->period_end);

        // An employee with no structure and no basic salary has nothing to pay.
        // Skipped rather than fataled, so one unconfigured person cannot block
        // the whole run.
        $basic = Decimal::amount($structure?->basic_salary ?? $employee->basic_salary ?? 0);

        if (Decimal::isZero($basic) && ! $structure) {
            return null;
        }

        $attendance = $this->attendanceSummary($payroll, $employee);
        $currencyId = $structure?->currency_id ?? $employee->currency_id ?? $payroll->currency_id;
        $rate = Decimal::rate($this->resolveRate($currencyId, $payroll));

        // Partial employment is resolved HERE, before the line is written, so
        // the stored basic_salary, the BASIC component and the totals all say
        // the same number. Prorating inside resolveComponents alone left the
        // line header showing a full month while the payslip showed a part.
        $share = $this->employmentShare($payroll, $employee, $attendance['working_days']);
        $fullBasic = $basic;
        $basic = Decimal::amount(bcmul($basic, $share['fraction'], Decimal::AMOUNT_SCALE));

        $line = PayrollLine::create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
            'salary_structure_id' => $structure?->id,
            'tax_bracket_set_id' => $taxSet->id,
            'currency_id' => $currencyId,
            'rate' => $rate,
            'working_days' => $attendance['working_days'],
            'present_days' => $attendance['present_days'],
            'absent_days' => $attendance['absent_days'],
            'paid_leave_days' => $attendance['paid_leave_days'],
            'unpaid_leave_days' => $attendance['unpaid_leave_days'],
            'overtime_hours' => $attendance['overtime_hours'],
            'basic_salary' => $basic,
            'created_by' => auth()->id(),
        ]);

        $components = $this->resolveComponents(
            $payroll, $employee, $structure, $line, $attendance, $basic, $systemComponents, $taxSet,
            $share, $fullBasic
        );

        foreach ($components as $sequence => $component) {
            PayrollLineComponent::create(array_merge($component, [
                'payroll_line_id' => $line->id,
                'sequence' => $component['sequence'] ?? $sequence,
                'created_by' => auth()->id(),
            ]));
        }

        return $this->summariseLine($line, $components, $basic, $rate);
    }

    /**
     * Days and hours from the attendance register.
     *
     * Holidays and rest days are excluded from `working_days`, so a month with
     * more Fridays does not quietly reduce a salaried employee's day rate.
     *
     * @return array<string, string>
     */
    private function attendanceSummary(Payroll $payroll, Employee $employee): array
    {
        $rows = Attendance::query()
            ->where('employee_id', $employee->id)
            ->forPeriod($payroll->period_start->toDateString(), $payroll->period_end->toDateString())
            ->get();

        $countWhere = fn (callable $fn) => (string) $rows->filter($fn)->count();

        $working = $rows->reject(fn (Attendance $a) => $a->status?->isNonWorkingDay() ?? false);

        $unpaidLeave = $rows->filter(function (Attendance $a) {
            return $a->status === AttendanceStatus::OnLeave
                && $a->leaveRequest?->leaveType
                && ! $a->leaveRequest->leaveType->is_paid;
        });

        $paidLeave = $rows->filter(function (Attendance $a) {
            return $a->status === AttendanceStatus::OnLeave
                && (! $a->leaveRequest?->leaveType || $a->leaveRequest->leaveType->is_paid);
        });

        return [
            // Falls back to the shift's expected working days when attendance
            // has not been entered — otherwise a run before the roster is
            // filled in would prorate everyone to zero.
            'working_days' => $working->isNotEmpty()
                ? (string) $working->count()
                : (string) $this->expectedWorkingDays($payroll, $employee),
            'present_days' => $countWhere(fn (Attendance $a) => $a->status?->isWorkedDay() ?? false),
            'absent_days' => $countWhere(fn (Attendance $a) => $a->status === AttendanceStatus::Absent),
            'paid_leave_days' => (string) $paidLeave->count(),
            'unpaid_leave_days' => (string) $unpaidLeave->count(),
            'overtime_hours' => Decimal::amount((string) $rows->sum(fn (Attendance $a) => (float) $a->overtime_hours)),
        ];
    }

    /**
     * How many days the employee's shift expects in this period.
     *
     * Jalali months run 29–31 days, so this counts the actual calendar rather
     * than assuming 30 — a fixed divisor would misprice every partial month.
     */
    private function expectedWorkingDays(Payroll $payroll, Employee $employee): int
    {
        $shift = $employee->shift ?? Shift::query()->where('is_default', true)->first();
        $days = 0;

        for (
            $cursor = $payroll->period_start->copy();
            $cursor->lte($payroll->period_end);
            $cursor->addDay()
        ) {
            if (! $shift || $shift->worksOn($cursor)) {
                $days++;
            }
        }

        return max($days, 1);
    }

    /**
     * Every component on this payslip, in resolution order.
     *
     * @param  array<string, string>  $attendance
     * @param  array<string, SalaryComponent>  $systemComponents
     * @return array<int, array<string, mixed>>
     */
    private function resolveComponents(
        Payroll $payroll,
        Employee $employee,
        ?SalaryStructure $structure,
        PayrollLine $line,
        array $attendance,
        string $basic,
        array $systemComponents,
        TaxBracketSet $taxSet,
        array $share,
        string $fullBasic,
    ): array {
        $workingDays = Decimal::amount($attendance['working_days']);
        $unpaidDays = Decimal::amount($attendance['unpaid_leave_days']);

        // $basic arrives already prorated for partial employment;
        // $fullBasic is the untouched figure the day rate derives from.

        // The day rate comes from the period's ACTUAL working days, so a
        // 31-day Jalali month and a 29-day one price a day differently — which
        // is what makes a partial month fair either way. It is derived from the
        // FULL basic: a day of unpaid leave costs the same whether or not the
        // employee also joined mid-month.
        $dayRate = Decimal::cmp($workingDays, '0') > 0
            ? bcdiv($fullBasic, $workingDays, Decimal::AMOUNT_SCALE)
            : '0.0000';

        $components = [];

        $components[] = [
            'salary_component_id' => null,
            'component_code' => 'BASIC',
            'component_name' => __('hr.basic_salary'),
            'component_type' => SalaryComponentType::Earning->value,
            'calculation_type' => ComponentCalculationType::Fixed->value,
            'amount' => $basic,
            'is_taxable' => true,
            'sequence' => 0,
        ];

        // Pass 1: fixed, percent-of-basic, per-day and per-hour lines.
        $structureLines = $structure
            ? $structure->lines()->with('component')->orderBy('sequence')->get()
            : collect();

        $deferred = [];

        foreach ($structureLines as $structureLine) {
            $component = $structureLine->component;

            if (! $component || ! $component->is_active) {
                continue;
            }

            $calculation = $structureLine->calculation_type ?? $component->calculation_type;

            if ($calculation->resolutionPass() === 2) {
                $deferred[] = [$structureLine, $component, $calculation];
                continue;
            }

            $row = $this->componentRow(
                $component,
                $calculation,
                $structureLine->amount ?? $component->amount,
                $structureLine->percentage ?? $component->percentage,
                $basic,
                $dayRate,
                $attendance,
                $structureLine->sequence ?: $component->sequence,
            );

            // `is_prorated` was configurable but never read. A transport
            // allowance marked prorated should shrink for a half-month joiner;
            // a fixed bonus marked not-prorated should not. Percent-of-basic
            // lines are already prorated through the basic they derive from.
            if ($component->is_prorated
                && $calculation !== ComponentCalculationType::PercentOfBasic
                && Decimal::cmp($share['fraction'], '1.0000') < 0) {
                $row['amount'] = Decimal::amount(
                    bcmul($row['amount'], $share['fraction'], Decimal::AMOUNT_SCALE)
                );
            }

            $components[] = $row;
        }

        // Overtime, priced from the shift's hourly rate.
        $overtime = Decimal::amount($attendance['overtime_hours']);

        if (Decimal::isPositive($overtime) && isset($systemComponents[SalaryComponent::CODE_OVERTIME])) {
            $hoursPerDay = Decimal::amount((string) ($employee->shift?->full_day_hours ?? 8));
            $hourRate = Decimal::cmp($hoursPerDay, '0') > 0
                ? bcdiv($dayRate, $hoursPerDay, Decimal::AMOUNT_SCALE)
                : '0.0000';

            $multiplier = Decimal::amount((string) (\App\Models\Hr\HrSetting::forBranch($payroll->branch_id)->overtime_multiplier ?? 1));

            $components[] = [
                'salary_component_id' => $systemComponents[SalaryComponent::CODE_OVERTIME]->id,
                'component_code' => SalaryComponent::CODE_OVERTIME,
                'component_name' => $systemComponents[SalaryComponent::CODE_OVERTIME]->name,
                'component_type' => SalaryComponentType::Earning->value,
                'calculation_type' => ComponentCalculationType::PerHour->value,
                'rate_or_percentage' => $hourRate,
                'base_amount' => $overtime,
                'amount' => Decimal::amount(bcmul(bcmul($overtime, $hourRate, 8), $multiplier, 8)),
                'is_taxable' => true,
                'account_id' => $systemComponents[SalaryComponent::CODE_OVERTIME]->account_id,
                'sequence' => 50,
            ];
        }

        // Pass 2: percent-of-gross, now that every other earning is known.
        $grossSoFar = $this->sumEarnings($components);

        foreach ($deferred as [$structureLine, $component, $calculation]) {
            $percentage = Decimal::amount($structureLine->percentage ?? $component->percentage ?? 0);

            $components[] = [
                'salary_component_id' => $component->id,
                'component_code' => $component->code,
                'component_name' => $component->name,
                'component_type' => $component->component_type->value,
                'calculation_type' => $calculation->value,
                'rate_or_percentage' => $percentage,
                'base_amount' => $grossSoFar,
                'amount' => bcdiv(bcmul($grossSoFar, $percentage, 8), '100', Decimal::AMOUNT_SCALE),
                'is_taxable' => (bool) $component->is_taxable,
                'account_id' => $component->account_id,
                'sequence' => $structureLine->sequence ?: $component->sequence,
            ];
        }

        // Unpaid leave, docked at the day rate.
        if (Decimal::isPositive($unpaidDays) && isset($systemComponents[SalaryComponent::CODE_UNPAID_LEAVE])) {
            $components[] = [
                'salary_component_id' => $systemComponents[SalaryComponent::CODE_UNPAID_LEAVE]->id,
                'component_code' => SalaryComponent::CODE_UNPAID_LEAVE,
                'component_name' => $systemComponents[SalaryComponent::CODE_UNPAID_LEAVE]->name,
                'component_type' => SalaryComponentType::Deduction->value,
                'calculation_type' => ComponentCalculationType::PerDay->value,
                'rate_or_percentage' => $dayRate,
                'base_amount' => $unpaidDays,
                'amount' => Decimal::amount(bcmul($unpaidDays, $dayRate, 8)),
                'is_taxable' => false,
                'sequence' => 80,
            ];
        }

        // Loan and advance recovery.
        $loanRecovery = $this->loanRecovery($employee, $payroll);

        if (Decimal::isPositive($loanRecovery) && isset($systemComponents[SalaryComponent::CODE_LOAN_RECOVERY])) {
            $components[] = [
                'salary_component_id' => $systemComponents[SalaryComponent::CODE_LOAN_RECOVERY]->id,
                'component_code' => SalaryComponent::CODE_LOAN_RECOVERY,
                'component_name' => $systemComponents[SalaryComponent::CODE_LOAN_RECOVERY]->name,
                'component_type' => SalaryComponentType::Deduction->value,
                'calculation_type' => ComponentCalculationType::Fixed->value,
                'amount' => $loanRecovery,
                'is_taxable' => false,
                'sequence' => 90,
            ];
        }

        // Tax, last: it is computed on taxable earnings less taxable
        // deductions, so everything else has to exist first.
        $taxable = $this->taxableIncome($components);

        $tax = $employee->is_tax_exempt
            ? ['tax' => '0.0000', 'marginal_rate' => '0.0000']
            : $this->wageTax->computeForPeriod(
                $taxable,
                $payroll->pay_frequency ?? \App\Enums\PayFrequency::Monthly,
                $taxSet
            );

        if (isset($systemComponents[SalaryComponent::CODE_WAGE_TAX])) {
            // Written even when zero, and even for an exempt employee, so the
            // payslip shows the exemption explicitly rather than omitting a
            // line and leaving the reader to wonder.
            $components[] = [
                'salary_component_id' => $systemComponents[SalaryComponent::CODE_WAGE_TAX]->id,
                'component_code' => SalaryComponent::CODE_WAGE_TAX,
                'component_name' => $systemComponents[SalaryComponent::CODE_WAGE_TAX]->name,
                'component_type' => SalaryComponentType::Deduction->value,
                'calculation_type' => ComponentCalculationType::Fixed->value,
                'rate_or_percentage' => $tax['marginal_rate'],
                'base_amount' => $taxable,
                'amount' => $tax['tax'],
                'is_taxable' => false,
                'sequence' => 100,
            ];
        }

        return $components;
    }

    /**
     * @param  array<string, string>  $attendance
     * @return array<string, mixed>
     */
    private function componentRow(
        SalaryComponent $component,
        ComponentCalculationType $calculation,
        mixed $amount,
        mixed $percentage,
        string $basic,
        string $dayRate,
        array $attendance,
        int $sequence,
    ): array {
        $value = match ($calculation) {
            ComponentCalculationType::Fixed => Decimal::amount($amount ?? 0),
            ComponentCalculationType::PercentOfBasic => bcdiv(
                bcmul($basic, Decimal::amount($percentage ?? 0), 8),
                '100',
                Decimal::AMOUNT_SCALE
            ),
            ComponentCalculationType::PerDay => Decimal::amount(
                bcmul(Decimal::amount($attendance['present_days']), Decimal::amount($amount ?? 0), 8)
            ),
            ComponentCalculationType::PerHour => Decimal::amount(
                bcmul(Decimal::amount($attendance['overtime_hours']), Decimal::amount($amount ?? 0), 8)
            ),
            // Percent-of-gross never reaches here; it is deferred to pass two.
            default => '0.0000',
        };

        return [
            'salary_component_id' => $component->id,
            'component_code' => $component->code,
            'component_name' => $component->name,
            'component_type' => $component->component_type->value,
            'calculation_type' => $calculation->value,
            'rate_or_percentage' => $calculation->usesPercentage()
                ? Decimal::amount($percentage ?? 0)
                : Decimal::amount($amount ?? 0),
            'base_amount' => $calculation === ComponentCalculationType::PercentOfBasic ? $basic : null,
            'amount' => $value,
            'is_taxable' => (bool) $component->is_taxable,
            'account_id' => $component->account_id,
            'sequence' => $sequence,
        ];
    }

    /**
     * What this run should recover across all of an employee's live loans.
     */
    private function loanRecovery(Employee $employee, Payroll $payroll): string
    {
        $loans = EmployeeLoan::query()
            ->where('employee_id', $employee->id)
            ->recoverableOn($payroll->period_end->toDateString())
            ->get();

        $total = '0.0000';

        foreach ($loans as $loan) {
            $total = Decimal::add($total, $loan->installmentDue());
        }

        return $total;
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    private function sumEarnings(array $components): string
    {
        $total = '0.0000';

        foreach ($components as $component) {
            if ($component['component_type'] === SalaryComponentType::Earning->value) {
                $total = Decimal::add($total, Decimal::amount($component['amount']));
            }
        }

        return $total;
    }

    /**
     * Taxable earnings less taxable deductions.
     *
     * A deduction flagged non-taxable (loan recovery, unpaid leave) reduces
     * take-home pay but not the tax base — recovering a loan is not a pay cut.
     *
     * @param  array<int, array<string, mixed>>  $components
     */
    private function taxableIncome(array $components): string
    {
        $total = '0.0000';

        foreach ($components as $component) {
            if (! ($component['is_taxable'] ?? false)) {
                continue;
            }

            $amount = Decimal::amount($component['amount']);

            $total = $component['component_type'] === SalaryComponentType::Earning->value
                ? Decimal::add($total, $amount)
                : Decimal::sub($total, $amount);
        }

        return Decimal::cmp($total, '0') < 0 ? '0.0000' : $total;
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    private function summariseLine(PayrollLine $line, array $components, string $basic, string $rate): PayrollLine
    {
        $gross = '0.0000';
        $deductions = '0.0000';
        $tax = '0.0000';

        foreach ($components as $component) {
            $amount = Decimal::amount($component['amount']);

            if ($component['component_type'] === SalaryComponentType::Earning->value) {
                $gross = Decimal::add($gross, $amount);
                continue;
            }

            if ($component['component_type'] === SalaryComponentType::Deduction->value) {
                $deductions = Decimal::add($deductions, $amount);

                if ($component['component_code'] === SalaryComponent::CODE_WAGE_TAX) {
                    $tax = $amount;
                }
            }
            // Employer contributions are a company cost, not a deduction from
            // the employee, so they touch neither gross nor net.
        }

        $net = Decimal::sub($gross, $deductions);
        $taxable = $this->taxableIncome($components);

        $line->forceFill([
            'gross_earnings' => $gross,
            'total_deductions' => $deductions,
            'taxable_income' => $taxable,
            'tax_amount' => $tax,
            'net_payable' => $net,
            // Frozen here, never recomputed on read.
            'base_gross' => Decimal::toBase($gross, $rate),
            'base_net' => Decimal::toBase($net, $rate),
            'payment_status' => PayrollLinePaymentStatus::Unpaid->value,
        ])->save();

        return $line;
    }

    /**
     * The exchange rate for a payslip's currency.
     *
     * Taken once at calculation and frozen on the line — a reprint must never
     * re-rate yesterday's salary at today's rate.
     */
    private function resolveRate(?string $currencyId, Payroll $payroll): string
    {
        if (! $currencyId) {
            return Decimal::rate($payroll->rate ?? 1);
        }

        $home = BranchContext::homeCurrency($payroll->branch_id);

        if ($home && $home->id === $currencyId) {
            return '1.00000000';
        }

        $currency = \App\Models\Administration\Currency::withoutGlobalScopes()->find($currencyId);

        return Decimal::rate($currency?->exchange_rate ?? $payroll->rate ?? 1);
    }
}

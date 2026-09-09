<?php

namespace App\Services\Accounting;

use App\Enums\CalendarType;
use App\Enums\FinancialPeriodStatus;
use App\Exceptions\Accounting\ClosedPeriodException;
use App\Exceptions\Accounting\SettlementException;
use App\Models\Accounting\FinancialPeriod;
use App\Models\Accounting\FiscalYear;
use App\Models\Administration\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

/**
 * Builds financial years and the months inside them, in the company's calendar.
 *
 * The whole reason this is not four lines of Carbon: a financial year here does
 * not start in January, and for most users it does not start in a Gregorian
 * month at all. Afghanistan's state fiscal year runs 1 Jadi to 30 Qaws; the
 * pre-1391 year ran 1 Hamal to 30 Hoot; a donor-funded NGO may keep 1 January.
 * All three have to be expressible, so the start is a (month, day) pair read in
 * the company's OWN calendar and the month arithmetic is done in that calendar
 * too — Jalali months are 31, 30 and 29 days and do not line up with Gregorian
 * ones, so stepping a Jalali year with Carbon::addMonth() drifts.
 *
 * Everything is PERSISTED as Gregorian, because every other date column in the
 * schema is. The Jalali calendar is an input and display concern, and the
 * posting guard never has to know about it.
 */
class FiscalYearService
{
    /** Twelve months in both calendars this supports. */
    private const MONTHS_IN_YEAR = 12;

    /**
     * The fiscal year containing a date, creating it (and its months) if absent.
     *
     * Auto-creation is deliberate: a user who posts an invoice on the first day
     * of a new year should not be met with "no financial period exists". Years
     * are created OPEN, so this grants no permission the user did not have — it
     * only removes an administrative step. Closing is always explicit.
     */
    public function ensureYearFor(string $date, string $branchId): FiscalYear
    {
        $existing = $this->yearFor($date, $branchId);

        if ($existing) {
            return $existing;
        }

        return $this->generate($date, $branchId);
    }

    /**
     * The fiscal year covering a date, or null when none has been generated.
     */
    public function yearFor(string $date, string $branchId): ?FiscalYear
    {
        return FiscalYear::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    /**
     * The month covering a date, or null when none has been generated.
     */
    public function periodFor(string $date, string $branchId): ?FinancialPeriod
    {
        return FinancialPeriod::withoutGlobalScopes()
            ->with('fiscalYear')
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->whereDate('start_date', '<=', $date)
            ->where(fn ($query) => $query
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', $date))
            ->first();
    }

    /**
     * Create the fiscal year containing $date, plus its twelve months.
     */
    public function generate(string $date, string $branchId): FiscalYear
    {
        return DB::transaction(function () use ($date, $branchId) {
            [$start, $end] = $this->boundsContaining($date);

            // A company that changes its fiscal-year start after trading has
            // begun would otherwise generate a year straddling ones that
            // already exist, and the guard resolves a date to whichever row it
            // happens to read first. Refuse instead of silently producing an
            // ambiguous calendar.
            $overlapping = FiscalYear::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())
                ->first();

            if ($overlapping) {
                throw SettlementException::make(
                    'A financial year already covers part of this range. Changing the '
                    . 'fiscal year start after years exist needs the later years removed first.',
                    [[
                        'requested' => $start->toDateString() . ' to ' . $end->toDateString(),
                        'existing' => $overlapping->name,
                        'existing_range' => $overlapping->start_date->toDateString()
                            . ' to ' . $overlapping->end_date->toDateString(),
                    ]]
                );
            }

            $year = FiscalYear::withoutGlobalScopes()->create([
                'name' => $this->yearName($end),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => FinancialPeriodStatus::Open->value,
                'branch_id' => $branchId,
                'created_by' => $this->actorId(),
            ]);

            foreach ($this->monthBounds($start) as $index => [$periodStart, $periodEnd]) {
                FinancialPeriod::withoutGlobalScopes()->create([
                    'fiscal_year_id' => $year->id,
                    'name' => $this->monthName($periodStart),
                    'start_date' => $periodStart->toDateString(),
                    // The last month is clamped to the year's end so the twelve
                    // periods tile the year exactly, with no gap and no overlap
                    // into the next one.
                    'end_date' => ($index === self::MONTHS_IN_YEAR - 1 ? $end : $periodEnd)->toDateString(),
                    'status' => FinancialPeriodStatus::Open->value,
                    'branch_id' => $branchId,
                    'created_by' => $this->actorId(),
                ]);
            }

            return $year->load('periods');
        });
    }

    // ======================================================
    // CLOSING
    // ======================================================

    /**
     * Close a year and every month in it.
     *
     * The months go too because a closed year with open months inside it reads
     * as though those months still accept postings, and the guard would have to
     * pick one of the two answers. Closing both keeps the screen honest.
     */
    public function closeYear(FiscalYear $year): FiscalYear
    {
        return DB::transaction(function () use ($year) {
            $year->periods()->update([
                'status' => FinancialPeriodStatus::Closed->value,
                'closed_at' => now(),
                'closed_by' => $this->actorId(),
            ]);

            $year->update([
                'status' => FinancialPeriodStatus::Closed->value,
                'closed_at' => now(),
                'closed_by' => $this->actorId(),
                'updated_by' => $this->actorId(),
            ]);

            return $year->refresh();
        });
    }

    /**
     * Reopen a year. Its months stay closed until reopened individually.
     *
     * Reopening the year alone is the conservative half: it lifts the backstop
     * without silently unlocking twelve months of signed-off ledger, so whoever
     * needs an adjustment has to say which month it belongs in.
     */
    public function reopenYear(FiscalYear $year): FiscalYear
    {
        $year->update([
            'status' => FinancialPeriodStatus::Open->value,
            'closed_at' => null,
            'closed_by' => null,
            'updated_by' => $this->actorId(),
        ]);

        return $year->refresh();
    }

    public function closePeriod(FinancialPeriod $period): FinancialPeriod
    {
        $period->update([
            'status' => FinancialPeriodStatus::Closed->value,
            'closed_at' => now(),
            'closed_by' => $this->actorId(),
            'updated_by' => $this->actorId(),
        ]);

        return $period->refresh();
    }

    public function reopenPeriod(FinancialPeriod $period): FinancialPeriod
    {
        if ($period->fiscalYear?->isClosed()) {
            throw ClosedPeriodException::yearClosed($period->fiscalYear->name);
        }

        $period->update([
            'status' => FinancialPeriodStatus::Open->value,
            'closed_at' => null,
            'closed_by' => null,
            'updated_by' => $this->actorId(),
        ]);

        return $period->refresh();
    }

    // ======================================================
    // CALENDAR ARITHMETIC
    // ======================================================

    /**
     * First and last Gregorian day of the fiscal year containing $date.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function boundsContaining(string $date): array
    {
        $anchor = Carbon::parse($date)->startOfDay();

        $start = $this->yearStartOnOrBefore($anchor);
        $end = $this->addYear($start)->subDay();

        return [$start, $end];
    }

    /**
     * The most recent fiscal-year start at or before the anchor.
     */
    private function yearStartOnOrBefore(Carbon $anchor): Carbon
    {
        [$month, $day] = $this->configuredStart();

        if ($this->calendar() === CalendarType::JALALI) {
            $jalali = Jalalian::fromCarbon($anchor);
            $candidate = $this->jalaliStart($jalali->getYear(), $month, $day);

            return $candidate->lessThanOrEqualTo($anchor)
                ? $candidate
                : $this->jalaliStart($jalali->getYear() - 1, $month, $day);
        }

        $candidate = $this->gregorianStart($anchor->year, $month, $day);

        return $candidate->lessThanOrEqualTo($anchor)
            ? $candidate
            : $this->gregorianStart($anchor->year - 1, $month, $day);
    }

    /**
     * The twelve month windows of a year, as [start, end] pairs.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function monthBounds(Carbon $yearStart): array
    {
        $bounds = [];

        for ($index = 0; $index < self::MONTHS_IN_YEAR; $index++) {
            $start = $this->addMonths($yearStart, $index);
            $end = $this->addMonths($yearStart, $index + 1)->subDay();

            $bounds[] = [$start, $end];
        }

        return $bounds;
    }

    private function addYear(Carbon $date): Carbon
    {
        return $this->addMonths($date, self::MONTHS_IN_YEAR);
    }

    /**
     * Step forward whole months in the company's calendar.
     *
     * Jalalian::addMonths() asserts a positive argument, so zero is handled here
     * rather than by the library.
     */
    private function addMonths(Carbon $date, int $months): Carbon
    {
        if ($months === 0) {
            return $date->copy();
        }

        if ($this->calendar() === CalendarType::JALALI) {
            return Jalalian::fromCarbon($date)->addMonths($months)->toCarbon()->startOfDay();
        }

        return $date->copy()->addMonthsNoOverflow($months)->startOfDay();
    }

    /**
     * A Jalali year start, with the day clamped into the target month.
     *
     * A company whose year starts on the 31st has no 31st in Mizan through Hoot,
     * and constructing that date throws rather than rolling over.
     */
    private function jalaliStart(int $year, int $month, int $day): Carbon
    {
        $daysInMonth = (new Jalalian($year, $month, 1))->getDaysOf($month);

        return (new Jalalian($year, $month, min($day, $daysInMonth)))
            ->toCarbon()
            ->startOfDay();
    }

    private function gregorianStart(int $year, int $month, int $day): Carbon
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        return Carbon::create($year, $month, min($day, $daysInMonth))->startOfDay();
    }

    // ======================================================
    // NAMING
    // ======================================================

    /**
     * Years are named for the calendar year their LAST day falls in.
     *
     * That single rule gives the expected answer in all three configurations:
     * a 1 Jadi 1403 – 30 Qaws 1404 year is "1404", which is what an Afghan
     * accountant calls it; a 1 Hamal – 30 Hoot 1404 year is also "1404"; and a
     * Gregorian January–December 2026 year is "2026".
     */
    private function yearName(Carbon $end): string
    {
        return $this->calendar() === CalendarType::JALALI
            ? (string) Jalalian::fromCarbon($end)->getYear()
            : (string) $end->year;
    }

    private function monthName(Carbon $start): string
    {
        if ($this->calendar() === CalendarType::JALALI) {
            $jalali = Jalalian::fromCarbon($start);

            // Afghan month names, not Iranian ones — this is an Afghan product
            // and "حمل" is not "فروردین" to the people reading the screen.
            return CalendarUtils::AFGHAN_MONTHS_NAME[$jalali->getMonth() - 1]
                . ' ' . $jalali->getYear();
        }

        return $start->format('F Y');
    }

    // ======================================================
    // COMPANY SETTINGS
    // ======================================================

    /**
     * @return array{0: int, 1: int}  [month, day] in the company's calendar
     */
    private function configuredStart(): array
    {
        $company = $this->company();

        // Unset means "whatever is normal for this calendar". There is no one
        // numeric default that is right for both: month 10 is Jadi — the Afghan
        // fiscal year — for a Jalali company, and October for a Gregorian one.
        [$defaultMonth, $defaultDay] = $this->calendar() === CalendarType::JALALI
            ? [10, 1]
            : [1, 1];

        $month = (int) ($company?->fiscal_year_start_month ?? $defaultMonth);
        $day = (int) ($company?->fiscal_year_start_day ?? $defaultDay);

        return [
            max(1, min(12, $month)),
            max(1, min(31, $day)),
        ];
    }

    private function calendar(): CalendarType
    {
        $type = $this->company()?->calendar_type;

        if ($type instanceof CalendarType) {
            return $type;
        }

        return CalendarType::tryFrom((string) $type) ?? CalendarType::JALALI;
    }

    /**
     * Company settings are read the same way BranchContext::costingMethod()
     * reads them — from the acting user, falling back to the only company on
     * the install so console backfills and queued jobs still resolve.
     */
    private function company(): ?Company
    {
        return Auth::user()?->company
            ?? Company::query()->withoutGlobalScopes()->orderBy('created_at')->first();
    }

    private function actorId(): ?string
    {
        return Auth::id();
    }
}

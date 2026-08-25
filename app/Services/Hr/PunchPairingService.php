<?php

namespace App\Services\Hr;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\PunchDirection;
use App\Models\Hr\Attendance;
use App\Models\Hr\AttendancePunch;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\Shift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns raw punches into one derived attendance row per employee per day.
 *
 * The hard part is that most affordable fingerprint terminals report a bare
 * timestamp with no in/out flag, so direction has to be inferred. The rules
 * below are deliberately conservative: where the data is genuinely ambiguous
 * the day is flagged for a human rather than guessed at, because a guessed
 * worked-hours figure flows straight into pay.
 */
class PunchPairingService
{
    /**
     * How far outside the shift a punch can still belong to that shift.
     *
     * Generous on the way out (people stay late) and tighter on the way in.
     */
    private const WINDOW_BEFORE_HOURS = 4;

    private const WINDOW_AFTER_HOURS = 8;

    /**
     * Recompute one employee's attendance for one date.
     *
     * Returns null when the day is locked by a posted payroll — the caller is
     * expected to surface that rather than silently skip it.
     */
    public function pairForDate(Employee $employee, Carbon $date, ?Shift $shift = null): ?Attendance
    {
        $shift = $shift ?? $employee->shift ?? $this->defaultShift($employee->branch_id);

        $existing = Attendance::withoutGlobalScopes()
            ->where('branch_id', $employee->branch_id)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->whereNull('deleted_at')
            ->first();

        if ($existing?->isLocked()) {
            return null;
        }

        $punches = $this->punchesInWindow($employee, $date, $shift);
        $computed = $this->computeFromPunches($punches, $date, $shift);
        $status = $this->resolveStatus($employee, $date, $shift, $computed);

        $attendance = Attendance::withoutGlobalScopes()->updateOrCreate(
            [
                'branch_id' => $employee->branch_id,
                'employee_id' => $employee->id,
                'date' => $date->toDateString(),
                'deleted_at' => null,
            ],
            [
                'shift_id' => $shift?->id,
                'check_in' => $computed['check_in'],
                'check_out' => $computed['check_out'],
                'worked_hours' => $computed['worked_hours'],
                'overtime_hours' => $computed['overtime_hours'],
                'break_minutes' => $computed['break_minutes'],
                'late_minutes' => $computed['late_minutes'],
                'early_out_minutes' => $computed['early_out_minutes'],
                'status' => $status,
                'needs_review' => $computed['needs_review'],
                'source' => AttendanceSource::Device->value,
                'created_by' => $existing?->created_by ?? auth()->id(),
            ]
        );

        // Tie the evidence to the day it produced, so a disputed figure can be
        // traced back to the punches behind it.
        if ($punches->isNotEmpty()) {
            AttendancePunch::withoutGlobalScopes()
                ->whereIn('id', $punches->pluck('id'))
                ->update(['attendance_id' => $attendance->id]);
        }

        return $attendance;
    }

    /**
     * @return Collection<int, AttendancePunch>
     */
    private function punchesInWindow(Employee $employee, Carbon $date, ?Shift $shift): Collection
    {
        [$from, $to] = $this->window($date, $shift);

        return AttendancePunch::withoutGlobalScopes()
            ->where('branch_id', $employee->branch_id)
            ->where('employee_id', $employee->id)
            ->where('is_ignored', false)
            ->whereNull('deleted_at')
            ->whereBetween('punched_at', [$from, $to])
            ->orderBy('punched_at')
            ->get();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(Carbon $date, ?Shift $shift): array
    {
        if (! $shift) {
            return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
        }

        $from = $shift->startOn($date)
            ->subMinutes((int) $shift->grace_in_minutes)
            ->subHours(self::WINDOW_BEFORE_HOURS);

        $to = $shift->endOn($date)
            ->addMinutes((int) $shift->grace_out_minutes)
            ->addHours(self::WINDOW_AFTER_HOURS);

        return [$from, $to];
    }

    /**
     * Reduce a day's punches to check-in, check-out and the derived figures.
     *
     * @param  Collection<int, AttendancePunch>  $punches
     */
    private function computeFromPunches(Collection $punches, Carbon $date, ?Shift $shift): array
    {
        $blank = [
            'check_in' => null,
            'check_out' => null,
            'worked_hours' => 0.0,
            'overtime_hours' => 0.0,
            'break_minutes' => 0,
            'late_minutes' => 0,
            'early_out_minutes' => 0,
            'needs_review' => false,
        ];

        if ($punches->isEmpty()) {
            return $blank;
        }

        $directed = $punches->filter(
            fn (AttendancePunch $p) => $this->direction($p) !== PunchDirection::Unknown
        );

        // Trust the device when it tells us the direction; infer only when it
        // does not. Mixing the two would let one mislabelled punch override the
        // ordering of the rest.
        [$checkIn, $checkOut, $breakMinutes] = $directed->count() === $punches->count()
            ? $this->fromDirectedPunches($punches)
            : $this->fromUndirectedPunches($punches);

        if (! $checkIn) {
            return $blank;
        }

        // A lone punch means someone forgot to clock out (or in). Recording a
        // zero-hour day silently would be worse than asking a human.
        if (! $checkOut) {
            return array_merge($blank, [
                'check_in' => $checkIn,
                'late_minutes' => $this->lateMinutes($checkIn, $date, $shift),
                'needs_review' => true,
            ]);
        }

        $grossMinutes = max(0, $checkIn->diffInMinutes($checkOut, false));
        $breakMinutes = max($breakMinutes, $shift ? (int) $shift->break_minutes : 0);
        $workedMinutes = max(0, $grossMinutes - $breakMinutes);
        $workedHours = round($workedMinutes / 60, 2);

        $fullDay = $shift ? (float) $shift->full_day_hours : 8.0;

        return [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'worked_hours' => $workedHours,
            'overtime_hours' => round(max(0, $workedHours - $fullDay), 2),
            'break_minutes' => $breakMinutes,
            'late_minutes' => $this->lateMinutes($checkIn, $date, $shift),
            'early_out_minutes' => $this->earlyOutMinutes($checkOut, $date, $shift),
            'needs_review' => false,
        ];
    }

    /**
     * Device-supplied directions: earliest IN, latest OUT, and any interior
     * OUT→IN gap counted as a break.
     *
     * @param  Collection<int, AttendancePunch>  $punches
     * @return array{0: ?Carbon, 1: ?Carbon, 2: int}
     */
    private function fromDirectedPunches(Collection $punches): array
    {
        $ins = $punches->filter(fn ($p) => $this->direction($p) === PunchDirection::In);
        $outs = $punches->filter(fn ($p) => $this->direction($p) === PunchDirection::Out);

        $checkIn = $ins->min('punched_at');
        $checkOut = $outs->max('punched_at');

        $checkIn = $checkIn ? Carbon::parse($checkIn) : null;
        $checkOut = $checkOut ? Carbon::parse($checkOut) : null;

        // An OUT before the first IN is a stray from the previous shift.
        if ($checkIn && $checkOut && $checkOut->lte($checkIn)) {
            $checkOut = null;
        }

        return [$checkIn, $checkOut, $this->breakMinutesBetween($punches, $checkIn, $checkOut)];
    }

    /**
     * No direction reported: first punch of the day is the arrival, last is the
     * departure, and interior punches pair up into breaks.
     *
     * @param  Collection<int, AttendancePunch>  $punches
     * @return array{0: ?Carbon, 1: ?Carbon, 2: int}
     */
    private function fromUndirectedPunches(Collection $punches): array
    {
        $times = $punches->pluck('punched_at')->map(fn ($t) => Carbon::parse($t))->values();

        if ($times->count() === 1) {
            return [$times->first(), null, 0];
        }

        $checkIn = $times->first();
        $checkOut = $times->last();

        // Interior punches come in pairs: leave for lunch, come back. An odd
        // one out is ignored rather than allowed to invert a break.
        $interior = $times->slice(1, max(0, $times->count() - 2))->values();
        $breakMinutes = 0;

        for ($i = 0; $i + 1 < $interior->count(); $i += 2) {
            $breakMinutes += max(0, $interior[$i]->diffInMinutes($interior[$i + 1], false));
        }

        return [$checkIn, $checkOut, $breakMinutes];
    }

    /**
     * @param  Collection<int, AttendancePunch>  $punches
     */
    private function breakMinutesBetween(Collection $punches, ?Carbon $checkIn, ?Carbon $checkOut): int
    {
        if (! $checkIn || ! $checkOut) {
            return 0;
        }

        $interior = $punches
            ->filter(function (AttendancePunch $p) use ($checkIn, $checkOut) {
                $at = Carbon::parse($p->punched_at);

                return $at->gt($checkIn) && $at->lt($checkOut);
            })
            ->sortBy('punched_at')
            ->values();

        $minutes = 0;
        $leftAt = null;

        foreach ($interior as $punch) {
            $at = Carbon::parse($punch->punched_at);

            if ($this->direction($punch) === PunchDirection::Out) {
                $leftAt = $leftAt ?? $at;
            } elseif ($leftAt) {
                $minutes += max(0, $leftAt->diffInMinutes($at, false));
                $leftAt = null;
            }
        }

        return $minutes;
    }

    private function lateMinutes(Carbon $checkIn, Carbon $date, ?Shift $shift): int
    {
        if (! $shift) {
            return 0;
        }

        $allowedFrom = $shift->startOn($date)->addMinutes((int) $shift->grace_in_minutes);

        return (int) max(0, $allowedFrom->diffInMinutes($checkIn, false));
    }

    private function earlyOutMinutes(Carbon $checkOut, Carbon $date, ?Shift $shift): int
    {
        if (! $shift) {
            return 0;
        }

        $allowedUntil = $shift->endOn($date)->subMinutes((int) $shift->grace_out_minutes);

        return (int) max(0, $checkOut->diffInMinutes($allowedUntil, false));
    }

    /**
     * Status precedence, highest first:
     *
     *   1. Approved leave  — wins over everything. Someone on approved leave was
     *      not absent, and a stray badge swipe at the office does not undo that.
     *   2. Holiday
     *   3. Non-working weekday for the shift
     *   4. Worked a full day  (late if past the grace period)
     *   5. Worked at least half a day
     *   6. Absent
     */
    private function resolveStatus(Employee $employee, Carbon $date, ?Shift $shift, array $computed): string
    {
        if ($this->hasApprovedLeave($employee, $date)) {
            return AttendanceStatus::OnLeave->value;
        }

        if ($this->isHoliday($employee->branch_id, $date)) {
            return AttendanceStatus::Holiday->value;
        }

        if ($shift && ! $shift->worksOn($date)) {
            return AttendanceStatus::Weekend->value;
        }

        $worked = (float) $computed['worked_hours'];

        if ($worked <= 0) {
            return AttendanceStatus::Absent->value;
        }

        $fullDay = $shift ? (float) $shift->full_day_hours : 8.0;
        $halfDay = $shift ? $shift->halfDayHours() : 4.0;

        if ($worked >= $fullDay) {
            return ((int) $computed['late_minutes']) > 0
                ? AttendanceStatus::Late->value
                : AttendanceStatus::Present->value;
        }

        if ($worked >= $halfDay) {
            return AttendanceStatus::HalfDay->value;
        }

        return AttendanceStatus::Absent->value;
    }

    private function hasApprovedLeave(Employee $employee, Carbon $date): bool
    {
        return LeaveRequest::withoutGlobalScopes()
            ->where('branch_id', $employee->branch_id)
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequestStatus::Approved->value)
            ->whereNull('deleted_at')
            ->whereDate('from_date', '<=', $date->toDateString())
            ->whereDate('to_date', '>=', $date->toDateString())
            ->exists();
    }

    private function isHoliday(string $branchId, Carbon $date): bool
    {
        return Holiday::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->covering($date)
            ->exists();
    }

    private function direction(AttendancePunch $punch): PunchDirection
    {
        return $punch->punch_direction instanceof PunchDirection
            ? $punch->punch_direction
            : (PunchDirection::tryFrom((string) $punch->punch_direction) ?? PunchDirection::Unknown);
    }

    private function defaultShift(string $branchId): ?Shift
    {
        return Shift::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->first();
    }
}

<?php

namespace App\Services\Hr;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Hr\Attendance;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\Shift;
use App\Support\BranchContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual attendance entry: the daily roster grid.
 *
 * The grid is the fallback that always works — no hardware, no employee logins,
 * one screen per day per department.
 */
class AttendanceService
{
    /**
     * Employees and their existing attendance for one date, ready to render.
     */
    public function roster(string $branchId, Carbon $date, ?string $departmentId = null, ?string $shiftId = null): array
    {
        $employees = Employee::query()
            ->employed()
            ->where('is_active', true)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($shiftId, fn ($q) => $q->where('shift_id', $shiftId))
            ->with(['department:id,name', 'designation:id,name', 'shift:id,name,start_time,end_time'])
            ->orderBy('full_name')
            ->get();

        $existing = Attendance::query()
            ->whereDate('date', $date->toDateString())
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $holiday = Holiday::query()->covering($date)->first();

        return [
            'date' => $date->toDateString(),
            'holiday' => $holiday?->name,
            'rows' => $employees->map(function (Employee $employee) use ($existing, $date, $holiday) {
                $row = $existing->get($employee->id);

                return [
                    'employee_id' => $employee->id,
                    'code' => $employee->code,
                    'full_name' => $employee->full_name,
                    'department_name' => $employee->department?->name,
                    'designation_name' => $employee->designation?->name,
                    'shift_id' => $employee->shift_id,
                    'shift_name' => $employee->shift?->name,
                    'attendance_id' => $row?->id,
                    // Pre-filled with what the day already is, so the common
                    // case is confirming rather than typing.
                    'status' => $row?->status?->value ?? $this->defaultStatus($employee, $date, $holiday !== null),
                    'check_in' => $row?->check_in?->format('H:i'),
                    'check_out' => $row?->check_out?->format('H:i'),
                    'overtime_hours' => $row ? (float) $row->overtime_hours : 0,
                    'remark' => $row?->remark,
                    'is_locked' => (bool) $row?->isLocked(),
                    'needs_review' => (bool) $row?->needs_review,
                ];
            })->values()->all(),
        ];
    }

    private function defaultStatus(Employee $employee, Carbon $date, bool $isHoliday): string
    {
        if ($isHoliday) {
            return AttendanceStatus::Holiday->value;
        }

        $shift = $employee->shift;

        if ($shift && ! $shift->worksOn($date)) {
            return AttendanceStatus::Weekend->value;
        }

        return AttendanceStatus::Present->value;
    }

    /**
     * Save a roster grid.
     *
     * Runs as one transaction so a partially applied day is impossible, and
     * refuses any row already consumed by a posted payroll rather than silently
     * skipping it — the user needs to know their edit did not take.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return int number of rows written
     */
    public function upsertRoster(Carbon $date, array $rows, ?string $defaultShiftId = null): int
    {
        $branchId = BranchContext::branchId();

        $employeeIds = collect($rows)->pluck('employee_id')->filter()->unique()->values();

        $locked = Attendance::query()
            ->whereDate('date', $date->toDateString())
            ->whereIn('employee_id', $employeeIds)
            ->locked()
            ->pluck('employee_id')
            ->all();

        if ($locked !== []) {
            $indexes = collect($rows)
                ->filter(fn (array $row) => in_array($row['employee_id'] ?? null, $locked, true))
                ->keys();

            throw ValidationException::withMessages(
                $indexes->mapWithKeys(fn ($i) => [
                    "rows.{$i}.status" => __('hr.validation.attendance_locked'),
                ])->all()
            );
        }

        $shifts = Shift::query()->get()->keyBy('id');

        return DB::transaction(function () use ($rows, $date, $branchId, $defaultShiftId, $shifts) {
            $written = 0;

            foreach ($rows as $row) {
                if (empty($row['employee_id'])) {
                    continue;
                }

                $shiftId = $row['shift_id'] ?? $defaultShiftId;
                $shift = $shiftId ? $shifts->get($shiftId) : null;

                $checkIn = $this->timestamp($date, $row['check_in'] ?? null);
                $checkOut = $this->timestamp($date, $row['check_out'] ?? null, $shift?->crosses_midnight ?? false);

                Attendance::updateOrCreate(
                    [
                        'branch_id' => $branchId,
                        'employee_id' => $row['employee_id'],
                        'date' => $date->toDateString(),
                        'deleted_at' => null,
                    ],
                    [
                        'shift_id' => $shiftId,
                        'status' => $row['status'] ?? AttendanceStatus::Present->value,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'worked_hours' => $this->workedHours($checkIn, $checkOut, $shift),
                        'overtime_hours' => (float) ($row['overtime_hours'] ?? 0),
                        'late_minutes' => $this->lateMinutes($checkIn, $date, $shift),
                        'remark' => $row['remark'] ?? null,
                        'source' => AttendanceSource::Roster->value,
                        'needs_review' => false,
                        'created_by' => auth()->id(),
                    ]
                );

                $written++;
            }

            return $written;
        });
    }

    private function timestamp(Carbon $date, ?string $time, bool $crossesMidnight = false): ?Carbon
    {
        if (! $time) {
            return null;
        }

        [$h, $m] = array_pad(explode(':', $time), 2, '0');
        $stamp = $date->copy()->setTime((int) $h, (int) $m);

        // A night shift clocking out at 02:00 belongs to the next calendar day.
        return $crossesMidnight && (int) $h < 12 ? $stamp->addDay() : $stamp;
    }

    private function workedHours(?Carbon $in, ?Carbon $out, ?Shift $shift): float
    {
        if (! $in || ! $out) {
            return 0;
        }

        $minutes = max(0, $in->diffInMinutes($out, false) - ($shift ? (int) $shift->break_minutes : 0));

        return round($minutes / 60, 2);
    }

    private function lateMinutes(?Carbon $in, Carbon $date, ?Shift $shift): int
    {
        if (! $in || ! $shift) {
            return 0;
        }

        $allowedFrom = $shift->startOn($date)->addMinutes((int) $shift->grace_in_minutes);

        return (int) max(0, $allowedFrom->diffInMinutes($in, false));
    }

    /**
     * Bulk helpers for the grid: mark everyone present, or copy the previous
     * working day forward.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function copyFrom(string $branchId, Carbon $source, Carbon $target): Collection
    {
        return Attendance::query()
            ->whereDate('date', $source->toDateString())
            ->get()
            ->map(fn (Attendance $a) => [
                'employee_id' => $a->employee_id,
                'shift_id' => $a->shift_id,
                'status' => $a->status?->value,
                'check_in' => $a->check_in?->format('H:i'),
                'check_out' => $a->check_out?->format('H:i'),
                'overtime_hours' => (float) $a->overtime_hours,
                'remark' => $a->remark,
            ]);
    }
}

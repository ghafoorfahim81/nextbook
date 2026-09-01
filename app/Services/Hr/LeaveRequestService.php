<?php

namespace App\Services\Hr;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Hr\Attendance;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The leave request state machine and its side effects.
 *
 * Approving leave does two things beyond flipping a status: it charges the
 * right allocation, and it writes `on_leave` attendance rows for the covered
 * days so the attendance register and payroll agree with the leave register
 * without either having to query the other.
 */
class LeaveRequestService
{
    public function __construct(
        private readonly LeaveBalanceService $balances,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Working days a request actually consumes.
     *
     * Weekends and holidays are skipped when the leave type says to, which is
     * why a week of annual leave costs five days but a week of maternity leave
     * costs seven.
     */
    public function countLeaveDays(Employee $employee, LeaveType $type, Carbon $from, Carbon $to, bool $isHalfDay = false): float
    {
        if ($isHalfDay) {
            return 0.5;
        }

        $shift = $employee->shift;
        $holidays = $this->holidayDates($employee->branch_id, $from, $to);

        $days = 0.0;

        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
            if ($type->excludes_holidays && in_array($cursor->toDateString(), $holidays, true)) {
                continue;
            }

            if ($type->excludes_weekends && $shift && ! $shift->worksOn($cursor)) {
                continue;
            }

            $days++;
        }

        return $days;
    }

    /**
     * @return array<int, string>
     */
    private function holidayDates(string $branchId, Carbon $from, Carbon $to): array
    {
        return Holiday::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->overlapping($from, $to)
            ->get()
            ->flatMap(fn (Holiday $h) => $h->coveredDates())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Move a request to a new status, enforcing the state machine.
     */
    public function transition(LeaveRequest $request, LeaveRequestStatus $target, array $context = []): LeaveRequest
    {
        if (! $request->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => __('hr.validation.invalid_leave_transition', [
                    'from' => $request->statusEnum()->getLabel(),
                    'to' => $target->getLabel(),
                ]),
            ]);
        }

        return DB::transaction(function () use ($request, $target, $context) {
            return match ($target) {
                LeaveRequestStatus::Pending => $this->submit($request),
                LeaveRequestStatus::Approved => $this->approve($request),
                LeaveRequestStatus::Rejected => $this->reject($request, $context['reason'] ?? null),
                LeaveRequestStatus::Cancelled => $this->cancel($request),
                LeaveRequestStatus::Withdrawn => $this->withdraw($request),
                default => $request,
            };
        });
    }

    private function submit(LeaveRequest $request): LeaveRequest
    {
        $request->forceFill([
            'status' => LeaveRequestStatus::Pending->value,
            'applied_at' => now(),
        ])->save();

        $this->notifyApprovers($request);

        return $request;
    }

    private function approve(LeaveRequest $request): LeaveRequest
    {
        $employee = $request->employee;
        $type = $request->leaveType;

        $this->assertSufficientBalance($request, $employee, $type);

        $allocation = $request->leave_allocation_id
            ? $request->allocation
            : $this->balances->allocationFor($employee, $type->id, $request->from_date);

        $request->forceFill([
            'status' => LeaveRequestStatus::Approved->value,
            'leave_allocation_id' => $allocation?->id,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ])->save();

        $this->writeAttendanceForLeave($request);
        $this->notifyEmployee($request, 'leave_approved');

        return $request;
    }

    /**
     * Unpaid leave is allowed to go negative — that is the point of it. Every
     * other type is capped at what the employee actually has.
     */
    private function assertSufficientBalance(LeaveRequest $request, Employee $employee, LeaveType $type): void
    {
        if ($type->deduct_from_salary || $type->accrual_method->value === 'unlimited') {
            return;
        }

        $balance = $this->balances->forType($employee, $type->id, $request->from_date);

        if (! $balance) {
            throw ValidationException::withMessages([
                'leave_type_id' => __('hr.validation.no_allocation'),
            ]);
        }

        if ($balance['available'] + 0.001 < (float) $request->days) {
            throw ValidationException::withMessages([
                'days' => __('hr.validation.insufficient_leave_balance', [
                    'available' => $balance['available'],
                    'requested' => (float) $request->days,
                ]),
            ]);
        }
    }

    private function reject(LeaveRequest $request, ?string $reason): LeaveRequest
    {
        $request->forceFill([
            'status' => LeaveRequestStatus::Rejected->value,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        $this->notifyEmployee($request, 'leave_rejected');

        return $request;
    }

    private function cancel(LeaveRequest $request): LeaveRequest
    {
        $wasApproved = $request->statusEnum() === LeaveRequestStatus::Approved;

        $request->forceFill([
            'status' => LeaveRequestStatus::Cancelled->value,
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
        ])->save();

        if ($wasApproved) {
            $this->removeFutureLeaveAttendance($request);
        }

        return $request;
    }

    private function withdraw(LeaveRequest $request): LeaveRequest
    {
        $request->forceFill([
            'status' => LeaveRequestStatus::Withdrawn->value,
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
        ])->save();

        $this->removeFutureLeaveAttendance($request);

        return $request;
    }

    /**
     * Write one `on_leave` attendance row per covered working day.
     *
     * Days already locked by a posted payroll are left alone — the payslip was
     * calculated from what was there at the time, and rewriting history under
     * it would make the two disagree.
     */
    public function writeAttendanceForLeave(LeaveRequest $request): int
    {
        $employee = $request->employee;
        $type = $request->leaveType;
        $shift = $employee->shift;
        $holidays = $this->holidayDates($employee->branch_id, $request->from_date, $request->to_date);

        $written = 0;

        for ($cursor = $request->from_date->copy(); $cursor->lte($request->to_date); $cursor->addDay()) {
            if ($type->excludes_holidays && in_array($cursor->toDateString(), $holidays, true)) {
                continue;
            }

            if ($type->excludes_weekends && $shift && ! $shift->worksOn($cursor)) {
                continue;
            }

            $locked = Attendance::withoutGlobalScopes()
                ->where('branch_id', $employee->branch_id)
                ->where('employee_id', $employee->id)
                ->whereDate('date', $cursor->toDateString())
                ->whereNull('deleted_at')
                ->whereNotNull('payroll_id')
                ->exists();

            if ($locked) {
                continue;
            }

            Attendance::withoutGlobalScopes()->updateOrCreate(
                [
                    'branch_id' => $employee->branch_id,
                    'employee_id' => $employee->id,
                    'date' => $cursor->toDateString(),
                    'deleted_at' => null,
                ],
                [
                    'shift_id' => $shift?->id,
                    'status' => AttendanceStatus::OnLeave->value,
                    'leave_request_id' => $request->id,
                    'source' => AttendanceSource::Manual->value,
                    'check_in' => null,
                    'check_out' => null,
                    'worked_hours' => 0,
                    'overtime_hours' => 0,
                    'late_minutes' => 0,
                    'early_out_minutes' => 0,
                    'needs_review' => false,
                    'created_by' => auth()->id(),
                ]
            );

            $written++;
        }

        return $written;
    }

    /**
     * Remove generated leave days from TODAY forward only.
     *
     * Past days already happened: if someone was away last week, cancelling the
     * request now does not retroactively put them at their desk. Only the
     * future part of the leave is undone.
     */
    public function removeFutureLeaveAttendance(LeaveRequest $request): int
    {
        return Attendance::withoutGlobalScopes()
            ->where('branch_id', $request->branch_id)
            ->where('leave_request_id', $request->id)
            ->whereNull('payroll_id')
            ->whereDate('date', '>=', Carbon::today()->toDateString())
            ->delete();
    }

    private function notifyApprovers(LeaveRequest $request): void
    {
        $manager = $request->employee?->manager;
        $user = $manager?->user;

        if (! $user) {
            return;
        }

        $this->notifications->notifyUser(
            user: $user,
            type: 'leave_request_pending',
            title: __('hr.notifications.leave_pending_title'),
            message: __('hr.notifications.leave_pending_body', [
                'employee' => $request->employee?->full_name ?? '',
                'days' => (float) $request->days,
            ]),
            data: [
                'leave_request_id' => $request->id,
                'employee_id' => $request->employee_id,
                'employee' => $request->employee?->full_name ?? '',
                'days' => (float) $request->days,
            ],
            dedupeKey: 'leave-pending:'.$request->id,
        );
    }

    private function notifyEmployee(LeaveRequest $request, string $type): void
    {
        $user = $request->employee?->user;

        if (! $user) {
            return;
        }

        $key = $type === 'leave_approved' ? 'leave_approved' : 'leave_rejected';

        $this->notifications->notifyUser(
            user: $user,
            type: $key,
            title: __("hr.notifications.{$key}_title"),
            message: __("hr.notifications.{$key}_body", [
                'from' => $request->from_date?->toDateString() ?? '',
                'to' => $request->to_date?->toDateString() ?? '',
            ]),
            data: [
                'leave_request_id' => $request->id,
                'from' => (string) ($request->from_date ?? ''),
                'to' => (string) ($request->to_date ?? ''),
            ],
            dedupeKey: $key.':'.$request->id,
        );
    }
}

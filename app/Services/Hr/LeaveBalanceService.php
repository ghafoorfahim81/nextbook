<?php

namespace App\Services\Hr;

use App\Enums\LeaveRequestStatus;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Leave balances, derived rather than stored.
 *
 *   available = entitled + carried_forward + adjustment
 *             − approved days − encashed − expired
 *
 * `pending` is reported separately and never subtracted: an employee should be
 * able to see what is awaiting a decision without it being taken off their
 * balance before anyone has agreed to it.
 *
 * A stored balance column would desync the moment a request is cancelled, an
 * approval is reversed, or an admin edits an adjustment. Because allocations
 * are one row per employee/type/period, deriving costs one grouped query even
 * for a whole department.
 */
class LeaveBalanceService
{
    /**
     * Balances for one employee, optionally narrowed to a single leave type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forEmployee(Employee $employee, ?string $leaveTypeId = null, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? Carbon::today();

        $rows = $this->baseQuery($employee->branch_id, $asOf)
            ->where('la.employee_id', $employee->id)
            ->when($leaveTypeId, fn ($q) => $q->where('la.leave_type_id', $leaveTypeId))
            ->get();

        return $rows->map(fn ($row) => $this->shape($row))->all();
    }

    /**
     * One leave type's balance, or null when the employee has no allocation for
     * it in the current period.
     */
    public function forType(Employee $employee, string $leaveTypeId, ?Carbon $asOf = null): ?array
    {
        return $this->forEmployee($employee, $leaveTypeId, $asOf)[0] ?? null;
    }

    /**
     * Balances for many employees in one query — for the leave balance report
     * and the allocations list, which would otherwise be N+1.
     *
     * @param  array<int, string>  $employeeIds
     * @return array<string, array<int, array<string, mixed>>> keyed by employee id
     */
    public function forEmployees(string $branchId, array $employeeIds, ?Carbon $asOf = null): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $rows = $this->baseQuery($branchId, $asOf ?? Carbon::today())
            ->whereIn('la.employee_id', $employeeIds)
            ->get();

        return $rows
            ->groupBy('employee_id')
            ->map(fn ($group) => $group->map(fn ($row) => $this->shape($row))->values()->all())
            ->all();
    }

    /**
     * Allocation rows for the period covering $asOf, with approved and pending
     * day counts folded in by the database.
     *
     * FILTER (WHERE …) rather than two joins: one pass over leave_requests, and
     * an employee with no requests still yields a row with zeroes instead of
     * dropping out of the result.
     */
    private function baseQuery(string $branchId, Carbon $asOf)
    {
        $date = $asOf->toDateString();

        return DB::table('leave_allocations as la')
            ->join('leave_types as lt', 'lt.id', '=', 'la.leave_type_id')
            ->leftJoin('leave_requests as lr', function ($join) {
                $join->on('lr.leave_allocation_id', '=', 'la.id')
                    ->whereNull('lr.deleted_at');
            })
            ->where('la.branch_id', $branchId)
            ->whereNull('la.deleted_at')
            ->whereNull('lt.deleted_at')
            ->whereDate('la.period_start', '<=', $date)
            ->whereDate('la.period_end', '>=', $date)
            ->groupBy(
                'la.id', 'la.employee_id', 'la.leave_type_id',
                'la.entitled_days', 'la.carried_forward_days', 'la.adjustment_days',
                'la.encashed_days', 'la.expired_days', 'la.period_start', 'la.period_end',
                'lt.name', 'lt.code', 'lt.colour', 'lt.is_paid'
            )
            ->select([
                'la.id as allocation_id',
                'la.employee_id',
                'la.leave_type_id',
                'la.entitled_days',
                'la.carried_forward_days',
                'la.adjustment_days',
                'la.encashed_days',
                'la.expired_days',
                'la.period_start',
                'la.period_end',
                'lt.name as leave_type_name',
                'lt.code as leave_type_code',
                'lt.colour as leave_type_colour',
                'lt.is_paid',
            ])
            ->selectRaw(
                'COALESCE(SUM(lr.days) FILTER (WHERE lr.status = ?), 0) as taken_days',
                [LeaveRequestStatus::Approved->value]
            )
            ->selectRaw(
                'COALESCE(SUM(lr.days) FILTER (WHERE lr.status = ?), 0) as pending_days',
                [LeaveRequestStatus::Pending->value]
            );
    }

    private function shape(object $row): array
    {
        $granted = (float) $row->entitled_days
            + (float) $row->carried_forward_days
            + (float) $row->adjustment_days;

        $consumed = (float) $row->taken_days
            + (float) $row->encashed_days
            + (float) $row->expired_days;

        return [
            'allocation_id' => $row->allocation_id,
            'employee_id' => $row->employee_id,
            'leave_type_id' => $row->leave_type_id,
            'leave_type_name' => $row->leave_type_name,
            'leave_type_code' => $row->leave_type_code,
            'leave_type_colour' => $row->leave_type_colour,
            'is_paid' => (bool) $row->is_paid,
            'period_start' => $row->period_start,
            'period_end' => $row->period_end,
            'entitled' => round((float) $row->entitled_days, 2),
            'carried' => round((float) $row->carried_forward_days, 2),
            'adjustment' => round((float) $row->adjustment_days, 2),
            'taken' => round((float) $row->taken_days, 2),
            'pending' => round((float) $row->pending_days, 2),
            'encashed' => round((float) $row->encashed_days, 2),
            'expired' => round((float) $row->expired_days, 2),
            'granted' => round($granted, 2),
            'available' => round($granted - $consumed, 2),
        ];
    }

    /**
     * The allocation a request should be charged against.
     */
    public function allocationFor(Employee $employee, string $leaveTypeId, Carbon $onDate): ?LeaveAllocation
    {
        return LeaveAllocation::withoutGlobalScopes()
            ->where('branch_id', $employee->branch_id)
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveTypeId)
            ->whereNull('deleted_at')
            ->covering($onDate->toDateString())
            ->first();
    }
}

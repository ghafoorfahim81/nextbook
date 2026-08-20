<?php

namespace App\Http\Middleware;

use App\Models\Hr\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the employee record behind the logged-in user for self-service.
 *
 * Deliberately a middleware inside the normal auth group rather than a separate
 * guard: a separate guard would drop CheckCompany and the BranchSpecific global
 * scope, and self-service must be branch-scoped like everything else.
 */
class EnsureEmployeeProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, __('hr.errors.no_employee_profile'));
        }

        // withoutGlobalScopes so the branch mismatch below is reported as a
        // mismatch rather than as "no employee record", which would send an
        // admin hunting for a record that exists.
        $employee = Employee::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('user_id', $user->id)
            ->first();

        if (! $employee) {
            abort(403, __('hr.errors.no_employee_profile'));
        }

        // Fail loudly rather than showing an empty screen: a user whose branch
        // does not match their employee record would otherwise see nothing at
        // all and have no idea why.
        if ($employee->branch_id !== $user->branch_id) {
            abort(403, __('hr.errors.employee_branch_mismatch'));
        }

        if (! $employee->self_service_enabled) {
            abort(403, __('hr.errors.self_service_disabled'));
        }

        app()->instance('current_employee', $employee);

        return $next($request);
    }
}

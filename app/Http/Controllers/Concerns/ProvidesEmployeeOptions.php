<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Resources\Hr\EmployeeOptionResource;
use App\Models\Hr\Employee;

/**
 * A first page for the employee pickers on HR forms.
 *
 * Those pickers are type-aheads against /search/employees, but opening one to
 * an empty list reads as broken; a short default list gives it something to
 * show while the user decides whether to type.
 */
trait ProvidesEmployeeOptions
{
    protected function employeeOptions(int $limit = 10): array
    {
        $employees = Employee::query()
            ->employed()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->limit($limit)
            ->get(['id', 'code', 'full_name', 'phone_number', 'email', 'department_id', 'designation_id', 'currency_id', 'is_active']);

        return EmployeeOptionResource::collection($employees)->resolve();
    }
}

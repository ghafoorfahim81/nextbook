<?php

namespace Database\Factories\Hr;

use App\Enums\LeaveAllocationSource;
use App\Models\Administration\Branch;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveAllocation;
use App\Models\Hr\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class LeaveAllocationFactory extends Factory
{
    protected $model = LeaveAllocation::class;

    public function definition(): array
    {
        return [
            // Branch, then company, then the two rows that belong to it:
            // Laravel expands a definition in order and hands each closure what
            // has already resolved.
            'branch_id' => Branch::factory(),
            // Read off the branch rather than built separately: a document's
            // company and its branch's company have to agree, and the composite
            // foreign key enforces that.
            'company_id' => fn (array $attributes) => Branch::withoutGlobalScopes()
                ->whereKey($attributes['branch_id'])
                ->value('company_id'),
            // In this allocation's own branch, so the employee and the
            // allocation are in the same books. A plain `Employee::factory()`
            // builds its own branch, and therefore its own company.
            'employee_id' => fn (array $attributes) => Employee::factory()
                ->create(['branch_id' => $attributes['branch_id']])
                ->getKey(),
            // A plain `LeaveType::factory()` falls back to a company of its own,
            // so the allocation granted a kind of leave another company defined.
            'leave_type_id' => fn (array $attributes) => LeaveType::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            'period_start' => Carbon::today()->startOfYear()->toDateString(),
            'period_end' => Carbon::today()->endOfYear()->toDateString(),
            'entitled_days' => 20,
            'carried_forward_days' => 0,
            'adjustment_days' => 0,
            'encashed_days' => 0,
            'expired_days' => 0,
            'source' => LeaveAllocationSource::AutoAccrual->value,
        ];
    }
}

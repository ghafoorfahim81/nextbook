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
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'period_start' => Carbon::today()->startOfYear()->toDateString(),
            'period_end' => Carbon::today()->endOfYear()->toDateString(),
            'entitled_days' => 20,
            'carried_forward_days' => 0,
            'adjustment_days' => 0,
            'encashed_days' => 0,
            'expired_days' => 0,
            'source' => LeaveAllocationSource::AutoAccrual->value,
            'branch_id' => Branch::factory(),
            // Read off the branch rather than built separately: a document's
            // company and its branch's company have to agree, and the composite
            // foreign key enforces that.
            'company_id' => fn (array $attributes) => Branch::withoutGlobalScopes()
                ->whereKey($attributes['branch_id'])
                ->value('company_id'),
        ];
    }
}

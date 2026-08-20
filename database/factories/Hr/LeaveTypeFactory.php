<?php

namespace Database\Factories\Hr;

use App\Enums\LeaveAccrualMethod;
use App\Models\Administration\Branch;
use App\Models\Hr\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'name' => 'Annual Leave',
            'code' => strtoupper(fake()->unique()->bothify('LT##')),
            'colour' => '#22c55e',
            'is_paid' => true,
            'accrual_method' => LeaveAccrualMethod::AnnualGrant->value,
            'days_per_year' => 20,
            'requires_approval' => true,
            'requires_attachment' => false,
            'deduct_from_salary' => false,
            'pro_rata_on_join' => true,
            'excludes_holidays' => true,
            'excludes_weekends' => true,
            'is_active' => true,
            'branch_id' => Branch::factory(),
        ];
    }

    public function unpaid(): static
    {
        return $this->state(fn () => [
            'name' => 'Unpaid Leave',
            'is_paid' => false,
            'accrual_method' => LeaveAccrualMethod::Unlimited->value,
            'deduct_from_salary' => true,
            'days_per_year' => null,
        ]);
    }

    /** Counts calendar days, like maternity leave. */
    public function includesNonWorkingDays(): static
    {
        return $this->state(fn () => [
            'excludes_holidays' => false,
            'excludes_weekends' => false,
        ]);
    }
}

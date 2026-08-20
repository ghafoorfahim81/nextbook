<?php

namespace Database\Factories\Hr;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Models\Administration\Branch;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeContract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class EmployeeContractFactory extends Factory
{
    protected $model = EmployeeContract::class;

    public function definition(): array
    {
        $start = Carbon::today()->subMonths(6);

        return [
            'employee_id' => Employee::factory(),
            'contract_number' => strtoupper(fake()->unique()->bothify('CON-####')),
            'contract_type' => ContractType::FixedTerm->value,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->toDateString(),
            'is_current' => true,
            'basic_salary' => fake()->numberBetween(8000, 90000),
            'working_hours_per_day' => 8,
            'working_days_per_week' => 6,
            'annual_leave_entitlement' => 20,
            'status' => ContractStatus::Active->value,
            'reminder_days_before' => 30,
            'branch_id' => Branch::factory(),
        ];
    }

    /**
     * A contract lapsing inside its reminder window — the state the renewal
     * reminder is supposed to pick up.
     */
    public function expiringInDays(int $days): static
    {
        return $this->state(fn () => [
            'end_date' => Carbon::today()->addDays($days)->toDateString(),
            'status' => ContractStatus::Active->value,
        ]);
    }
}

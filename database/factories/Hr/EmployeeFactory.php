<?php

namespace Database\Factories\Hr;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\Administration\Branch;
use App\Models\Hr\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'code' => strtoupper(fake()->unique()->bothify('EMP-####')),
            'first_name' => $first,
            'last_name' => $last,
            // Written anyway by the model's saving hook; set here so a factory
            // ->make() (which never saves) still has it populated.
            'full_name' => trim($first.' '.$last),
            'father_name' => fake()->firstName('male'),
            'gender' => fake()->randomElement(Gender::values()),
            'marital_status' => fake()->randomElement(MaritalStatus::values()),
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-20 years')->format('Y-m-d'),
            'national_id' => fake()->unique()->numerify('##########'),
            'phone_number' => fake()->unique()->numerify('07########'),
            'email' => fake()->unique()->safeEmail(),
            'employment_type' => EmploymentType::Permanent->value,
            'employment_status' => EmploymentStatus::Active->value,
            'joining_date' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'basic_salary' => fake()->numberBetween(8000, 90000),
            'is_active' => true,
            'branch_id' => Branch::factory(),
        ];
    }

    public function separated(): static
    {
        return $this->state(fn () => [
            'employment_status' => EmploymentStatus::Resigned->value,
            'separation_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'separation_reason' => fake()->sentence(),
        ]);
    }

    public function onProbation(): static
    {
        return $this->state(fn () => [
            'employment_status' => EmploymentStatus::Probation->value,
        ]);
    }
}

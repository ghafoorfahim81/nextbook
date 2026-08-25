<?php

namespace Database\Factories\Hr;

use App\Enums\EmploymentType;
use App\Enums\JobOpeningStatus;
use App\Models\Administration\Branch;
use App\Models\Hr\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobOpeningFactory extends Factory
{
    protected $model = JobOpening::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('JOB-####')),
            'title' => fake()->jobTitle(),
            'employment_type' => EmploymentType::Permanent->value,
            'vacancies' => 1,
            'description' => fake()->paragraph(),
            'location' => fake()->city(),
            'posted_date' => now()->toDateString(),
            'closing_date' => now()->addDays(30)->toDateString(),
            'status' => JobOpeningStatus::Draft->value,
            'branch_id' => Branch::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => JobOpeningStatus::Published->value]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => JobOpeningStatus::Closed->value]);
    }
}

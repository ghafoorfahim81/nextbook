<?php

namespace Database\Factories\Hr;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Models\Administration\Branch;
use App\Models\Hr\JobApplication;
use App\Models\Hr\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobApplicationFactory extends Factory
{
    protected $model = JobApplication::class;

    public function definition(): array
    {
        return [
            'job_opening_id' => JobOpening::factory(),
            'application_number' => strtoupper(fake()->unique()->bothify('APP-#####')),
            'full_name' => fake()->name(),
            'father_name' => fake()->firstName('male'),
            'gender' => fake()->randomElement(Gender::values()),
            'date_of_birth' => fake()->dateTimeBetween('-50 years', '-20 years')->format('Y-m-d'),
            'national_id' => fake()->unique()->numerify('##########'),
            'phone_number' => fake()->unique()->numerify('07########'),
            'email' => fake()->unique()->safeEmail(),
            'years_of_experience' => fake()->numberBetween(0, 20),
            'source' => ApplicationSource::Website->value,
            'status' => ApplicationStatus::Applied->value,
            'applied_date' => now()->toDateString(),
            'branch_id' => Branch::factory(),
        ];
    }

    public function shortlisted(): static
    {
        return $this->state(fn () => ['status' => ApplicationStatus::Shortlisted->value]);
    }

    public function offered(): static
    {
        return $this->state(fn () => [
            'status' => ApplicationStatus::Offered->value,
            'offered_date' => now()->toDateString(),
            'offered_salary' => 45000,
        ]);
    }
}

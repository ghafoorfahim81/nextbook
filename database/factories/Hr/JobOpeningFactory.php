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
            // Read off the branch rather than built separately: a document's
            // company and its branch's company have to agree, and the composite
            // foreign key enforces that.
            'company_id' => fn (array $attributes) => Branch::withoutGlobalScopes()
                ->whereKey($attributes['branch_id'])
                ->value('company_id'),
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

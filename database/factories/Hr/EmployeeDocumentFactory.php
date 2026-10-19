<?php

namespace Database\Factories\Hr;

use App\Enums\EmployeeDocumentType;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'document_type' => EmployeeDocumentType::Tazkira->value,
            'document_number' => fake()->unique()->numerify('##########'),
            'issued_by' => fake()->company(),
            'issue_date' => Carbon::today()->subYears(2)->toDateString(),
            'expiry_date' => Carbon::today()->addYear()->toDateString(),
            'is_verified' => false,
            'reminder_days_before' => 30,
            // Read off the employee: the record has no branch of its own, and
            // its company has to be the employee's.
            'company_id' => fn (array $attributes) => Employee::withoutGlobalScopes()
                ->whereKey($attributes['employee_id'])
                ->value('company_id'),
        ];
    }

    public function expiringInDays(int $days): static
    {
        return $this->state(fn () => [
            'expiry_date' => Carbon::today()->addDays($days)->toDateString(),
        ]);
    }
}

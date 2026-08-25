<?php

namespace Database\Factories\Hr;

use App\Enums\PayFrequency;
use App\Enums\PayrollStatus;
use App\Models\Administration\Branch;
use App\Models\Hr\Payroll;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    public function definition(): array
    {
        $start = Carbon::parse('2026-08-01');

        return [
            'number' => (string) fake()->unique()->numberBetween(1, 99999),
            'name' => 'Payroll '.$start->format('M Y'),
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'pay_date' => $start->copy()->endOfMonth()->toDateString(),
            'period_label' => '1405-05',
            'pay_frequency' => PayFrequency::Monthly->value,
            'rate' => 1,
            'status' => PayrollStatus::Draft->value,
            'branch_id' => Branch::factory(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'status' => PayrollStatus::Posted->value,
            'posted_at' => now(),
        ]);
    }

    public function forPeriod(string $from, string $to): static
    {
        return $this->state(fn () => [
            'period_start' => $from,
            'period_end' => $to,
            'pay_date' => $to,
        ]);
    }
}

<?php

namespace Database\Factories\Hr;

use App\Enums\HolidayType;
use App\Models\Administration\Branch;
use App\Models\Hr\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'date' => Carbon::today()->toDateString(),
            'end_date' => null,
            'holiday_type' => HolidayType::Public->value,
            'is_recurring' => false,
            'is_paid' => true,
            'branch_id' => Branch::factory(),
        ];
    }

    public function on(string $date): static
    {
        return $this->state(fn () => ['date' => $date]);
    }

    public function spanning(string $from, string $to): static
    {
        return $this->state(fn () => ['date' => $from, 'end_date' => $to]);
    }
}

<?php

namespace Database\Factories\Hr;

use App\Models\Administration\Branch;
use App\Models\Hr\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'name' => 'General Shift',
            'code' => strtoupper(fake()->unique()->bothify('SH##')),
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'crosses_midnight' => false,
            'break_minutes' => 60,
            'grace_in_minutes' => 15,
            'grace_out_minutes' => 0,
            'full_day_hours' => 8,
            'half_day_hours' => 4,
            // Sat, Sun, Mon, Tue, Wed, Thu -- Friday is the rest day.
            'working_days' => [6, 7, 1, 2, 3, 4],
            'is_default' => true,
            'is_active' => true,
            'branch_id' => Branch::factory(),
        ];
    }

    public function night(): static
    {
        return $this->state(fn () => [
            'name' => 'Night Shift',
            'start_time' => '20:00:00',
            'end_time' => '04:00:00',
            'crosses_midnight' => true,
            'break_minutes' => 30,
        ]);
    }
}

<?php

namespace Database\Factories\Administration;

use App\Models\Administration\Branch;
use App\Models\Administration\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

class SizeFactory extends Factory
{
    protected $model = Size::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'is_active' => true,
            'is_main' => false,
            'branch_id' => Branch::factory(),
        ];
    }
}

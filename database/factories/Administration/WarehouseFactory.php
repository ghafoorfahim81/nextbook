<?php

namespace Database\Factories\Administration;

use App\Models\Administration\Branch;
use App\Models\Administration\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Warehouse',
            'address' => fake()->optional()->address(),
            'is_main' => false,
            'branch_id' => Branch::factory(),
            'is_active' => true,
        ];
    }
}

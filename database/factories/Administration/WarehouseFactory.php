<?php

namespace Database\Factories\Administration;

use App\Models\Administration\Branch;
use App\Models\Administration\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'id' => Str::ulid(),
            'name' => fake()->name(),
            'address' => fake()->word(),
            'is_main' => fake()->boolean(),
            'branch_id' => Branch::factory(),
        ];
    }
}


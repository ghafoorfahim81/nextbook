<?php

namespace Database\Factories\Administration;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Branch;
use App\Models\Administration\Store;
use App\Models\User;

class StoreFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Store::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'id' => Str::ulid(), // Generate a UUID for the department ID
            'name' => fake()->name(),
            'address' => fake()->word(),
            'is_main' => fake()->boolean(),
            'branch_id' => Branch::factory(),
        ];
    }
}

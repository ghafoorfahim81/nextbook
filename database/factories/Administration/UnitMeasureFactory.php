<?php

namespace Database\Factories\Administration;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Branch;
use App\Models\Administration\Quantity;
use App\Models\Administration\UnitMeasure;
use App\Models\User;

class UnitMeasureFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = UnitMeasure::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'unit' => fake()->word(),
            'symbol' => fake()->word(),
            'branch_id' => Branch::factory(),
            'quantity_id' => Quantity::factory(),
            'value' => fake()->randomFloat(0, 0, 9999.),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}

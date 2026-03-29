<?php

namespace Database\Factories\Administration;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Administration\Branch;
use App\Models\Administration\Quantity;
use App\Models\Administration\UnitMeasure;

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
            'name' => fake()->unique()->word(),
            'unit' => fake()->randomElement(['1', '10', '100']),
            'symbol' => fake()->unique()->lexify('U??'),
            'branch_id' => Branch::factory(),
            'quantity_id' => Quantity::factory(),
            'value' => fake()->randomFloat(2, 1, 1000),
            'is_main' => false,
            'is_active' => true,
        ];
    }
}

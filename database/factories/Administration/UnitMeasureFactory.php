<?php

namespace Database\Factories\Administration;

use App\Models\Administration\Company;
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
            // Company before quantity: Laravel expands a definition in order, so
            // the quantity can only be built in this company if it is already
            // resolved. A plain `Quantity::factory()` falls back to a company of
            // its own, so the unit measured something from other books.
            'company_id' => Company::factory(),
            'quantity_id' => fn (array $attributes) => Quantity::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            'value' => fake()->randomFloat(2, 1, 1000),
            'is_main' => false,
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories\Administration;

use App\Models\Administration\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Administration\Branch;
use App\Models\Administration\Quantity;

class QuantityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Quantity::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'quantity' => fake()->word(),
            'unit' => fake()->randomElement(['piece', 'kg', 'liter']),
            'symbol' => strtoupper(fake()->lexify('??')),
            'slug' => $slug,
            'company_id' => Company::factory(),
            'is_main' => false,
            'is_active' => true,
        ];
    }
}

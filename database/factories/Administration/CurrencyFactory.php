<?php

namespace Database\Factories\Administration;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Administration\Branch;
use App\Models\Administration\Currency;

class CurrencyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Currency::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->currencyCode().' Currency',
            'code' => fake()->unique()->currencyCode(),
            'symbol' => fake()->randomElement(['$', 'Af', 'Rs', 'EUR']),
            'format' => '1,0.00',
            'exchange_rate' => fake()->randomFloat(4, 0.1, 500),
            'is_active' => true,
            'is_base_currency' => false,
            'flag' => fake()->optional()->lexify('??').'.png',
            'branch_id' => Branch::factory(),
            'is_main' => false,
        ];
    }
}

<?php

namespace Database\Factories\Administration;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Branch;
use App\Models\Administration\Currency;
use App\Models\User;

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
            'name' => $this->faker->name(),
            'code' => $this->faker->word(),
            'symbol' => $this->faker->word(),
            'format' => $this->faker->word(),
            'exchange_rate' => $this->faker->randomFloat(0, 0, 999.),
            'is_active' => $this->faker->boolean(),
            'is_base_currency' => false,
            'flag' => $this->faker->word(),
            'branch_id' => Branch::factory(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}

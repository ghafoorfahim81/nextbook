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
            'exchange_rate' => $this->faker->randomFloat(0, 0, 9999999999.),
            'is_active' => $this->faker->boolean(),
            'flag' => $this->faker->word(),
            'branch_id' => Branch::factory(),
            'created_by' => User::factory()->create()->created_by,
            'updated_by' => User::factory()->create()->updated_by,
        ];
    }
}

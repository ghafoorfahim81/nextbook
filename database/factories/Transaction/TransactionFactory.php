<?php

namespace Database\Factories\Transaction;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Currency;
use App\Models\Transaction\Transaction;
use App\Models\User;

class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'transactionable' => fake()->word(),
            'amount' => fake()->randomFloat(0, 0, 9999999999.),
            'currency_id' => Currency::factory(),
            'rate' => fake()->randomFloat(0, 0, 9999999999.),
            'date' => fake()->date(),
            'type' => fake()->word(),
            'remark' => fake()->text(),
            'created_by' => User::factory()->create()->created_by,
            'updated_by' => User::factory()->create()->updated_by,
        ];
    }
}

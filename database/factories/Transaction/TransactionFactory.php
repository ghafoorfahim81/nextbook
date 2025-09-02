<?php

namespace Database\Factories\Transaction;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Currency;
use App\Models\Account\Account;
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
            'amount' => fake()->randomFloat(0, 0, 9999999999.),
            'account_id' => Account::factory(),
            'currency_id' => Currency::where('is_active', true)->inRandomOrder()->first()->id,
            'rate' => fake()->randomFloat(0, 0, 9999999999.),
            'date' => fake()->date(),
            'type' => fake()->randomElement(['debit', 'credit']),
            'remark' => fake()->text(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}

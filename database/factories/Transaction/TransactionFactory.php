<?php

namespace Database\Factories\Transaction;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Currency;
use App\Models\Account\Account;
use App\Models\Transaction\Transaction;
use App\Models\User;
use App\Models\Ledger\Ledger;

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
            'amount' => fake()->randomFloat(2, 0, 100000),
            'account_id' => Account::inRandomOrder()->first()->id,
            'ledger_id' => Ledger::factory(),
            'currency_id' => Currency::where('is_active', true)->inRandomOrder()->first()->id,
            'rate' => fake()->randomFloat(2, 0.5, 2.0),
            'date' => fake()->date(),
            'type' => fake()->randomElement(['debit', 'credit']),
            'remark' => fake()->text(),
            'reference_type' => fake()->randomElement(['purchase', 'sale', 'expense', 'income']),
            'reference_id' => \App\Models\Purchase\Purchase::factory(),
        ];
    }
}

<?php

namespace Database\Factories\Purchase;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Ledger\Ledger;
use App\Models\Purchase\Purchase;
use App\Models\Transaction\Transaction;
use App\Models\User;

class PurchaseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Purchase::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'number' => fake()->word(),
            'supplier_id' => Ledger::factory(),
            'date' => fake()->date(),
            'transaction_id' => Transaction::factory(),
            'discount' => fake()->randomFloat(0, 100),
            'discount_type' => fake()->randomElement(['currency', 'percentage']),
            'type' => fake()->randomElement(['cash', 'credit']),
            'description' => fake()->text(),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}

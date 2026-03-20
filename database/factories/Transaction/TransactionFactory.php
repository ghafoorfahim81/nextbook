<?php

namespace Database\Factories\Transaction;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Administration\Currency;
use App\Models\Transaction\Transaction;
use App\Models\Administration\Branch;
use App\Enums\TransactionStatus;

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
            'currency_id' => Currency::factory(),
            'rate' => fake()->randomFloat(4, 0.5, 2.0),
            'date' => fake()->date(),
            'voucher_number' => fake()->optional()->bothify('VCH-#####'),
            'reference_type' => null,
            'reference_id' => null,
            'status' => TransactionStatus::POSTED->value,
            'branch_id' => Branch::factory(),
            'remark' => fake()->optional()->sentence(),
        ];
    }
}

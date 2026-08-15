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
            // The branch's functional currency, falling back to the voucher's
            // own when a bare branch has no base currency provisioned yet.
            'base_currency_id' => fn (array $attributes) => Currency::withoutGlobalScopes()
                ->where('branch_id', $attributes['branch_id'] ?? null)
                ->where('is_base_currency', true)
                ->value('id') ?? $attributes['currency_id'],
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

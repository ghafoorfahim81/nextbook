<?php

namespace Database\Factories\Sale;

use App\Enums\SaleReturnReason;
use App\Enums\TransactionStatus;
use App\Models\Administration\Branch;
use App\Models\Ledger\Ledger;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleReturnFactory extends Factory
{
    protected $model = SaleReturn::class;

    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1, 999999),
            'sale_id' => Sale::factory(),
            'customer_id' => Ledger::factory(),
            'date' => fake()->date(),
            'reason' => fake()->randomElement(SaleReturnReason::values()),
            'description' => fake()->optional()->sentence(),
            'status' => TransactionStatus::POSTED->value,
            'branch_id' => Branch::factory(),
        ];
    }
}

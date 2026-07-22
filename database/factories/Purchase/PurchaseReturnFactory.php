<?php

namespace Database\Factories\Purchase;

use App\Enums\PurchaseReturnReason;
use App\Enums\TransactionStatus;
use App\Models\Administration\Branch;
use App\Models\Ledger\Ledger;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseReturnFactory extends Factory
{
    protected $model = PurchaseReturn::class;

    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1, 999999),
            'purchase_id' => Purchase::factory(),
            'supplier_id' => Ledger::factory(),
            'date' => fake()->date(),
            'reason' => fake()->randomElement(PurchaseReturnReason::values()),
            'description' => fake()->optional()->sentence(),
            'status' => TransactionStatus::POSTED->value,
            'branch_id' => Branch::factory(),
        ];
    }
}

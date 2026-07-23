<?php

namespace Database\Factories\Purchase;

use App\Enums\PurchaseOrderStatus;
use App\Models\Administration\Branch;
use App\Models\Ledger\Ledger;
use App\Models\Purchase\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1, 999999),
            'date' => fake()->date(),
            'delivery_date' => fake()->optional()->date(),
            'supplier_id' => Ledger::factory(),
            'discount' => 0,
            'discount_type' => null,
            'status' => PurchaseOrderStatus::POSTED->value,
            'note' => fake()->optional()->sentence(),
            'branch_id' => Branch::factory(),
        ];
    }
}

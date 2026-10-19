<?php

namespace Database\Factories\Sale;

use App\Enums\SaleOrderStatus;
use App\Models\Administration\Branch;
use App\Models\Ledger\Ledger;
use App\Models\Sale\SaleOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleOrderFactory extends Factory
{
    protected $model = SaleOrder::class;

    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1, 999999),
            'date' => fake()->date(),
            'delivery_date' => fake()->optional()->date(),
            'customer_id' => Ledger::factory(),
            'discount' => 0,
            'discount_type' => null,
            'status' => SaleOrderStatus::POSTED->value,
            'note' => fake()->optional()->sentence(),
            'branch_id' => Branch::factory(),
            // Read off the branch rather than built separately: a document's
            // company and its branch's company have to agree, and the composite
            // foreign key enforces that.
            'company_id' => fn (array $attributes) => Branch::withoutGlobalScopes()
                ->whereKey($attributes['branch_id'])
                ->value('company_id'),
        ];
    }
}

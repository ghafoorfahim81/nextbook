<?php

namespace Database\Factories\Sale;

use App\Enums\DiscountType;
use App\Enums\SalePurchaseType;
use App\Enums\TransactionStatus;
use App\Models\Administration\Branch;
use App\Models\Ledger\Ledger;
use App\Models\Sale\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1, 999999),
            'customer_id' => Ledger::factory(),
            'date' => fake()->date(),
            'discount' => fake()->randomFloat(2, 0, 30),
            'discount_type' => DiscountType::PERCENTAGE->value,
            'type' => SalePurchaseType::Cash->value,
            'description' => fake()->optional()->sentence(),
            'status' => TransactionStatus::POSTED->value,
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

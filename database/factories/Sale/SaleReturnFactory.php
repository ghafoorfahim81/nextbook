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
            // Read off the branch rather than built separately: a document's
            // company and its branch's company have to agree, and the composite
            // foreign key enforces that.
            'company_id' => fn (array $attributes) => Branch::withoutGlobalScopes()
                ->whereKey($attributes['branch_id'])
                ->value('company_id'),
        ];
    }
}

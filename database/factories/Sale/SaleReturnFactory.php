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
            // Branch, then company, then the sale, then the sale's customer.
            // Laravel expands a definition in order and hands each closure what
            // has already resolved, so this order is what lets each one be read
            // off the one before instead of inventing its own.
            'branch_id' => Branch::factory(),
            // Read off the branch rather than built separately: a document's
            // company and its branch's company have to agree, and the composite
            // foreign key enforces that.
            'company_id' => fn (array $attributes) => Branch::withoutGlobalScopes()
                ->whereKey($attributes['branch_id'])
                ->value('company_id'),
            // Built in this return's own branch. A plain `Sale::factory()`
            // would build its own branch, and therefore its own company.
            'sale_id' => fn (array $attributes) => Sale::factory()
                ->create(['branch_id' => $attributes['branch_id']])
                ->getKey(),
            // The customer of the sale being returned — not a new one, which is
            // what `Ledger::factory()` gave: a return whose customer had never
            // bought anything, in a company of their own.
            'customer_id' => fn (array $attributes) => Sale::withoutGlobalScopes()
                ->whereKey($attributes['sale_id'])
                ->value('customer_id'),
            'number' => fake()->unique()->numberBetween(1, 999999),
            'date' => fake()->date(),
            'reason' => fake()->randomElement(SaleReturnReason::values()),
            'description' => fake()->optional()->sentence(),
            'status' => TransactionStatus::POSTED->value,
        ];
    }
}

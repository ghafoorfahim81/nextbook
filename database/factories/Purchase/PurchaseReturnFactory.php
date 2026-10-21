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
            // Branch, then company, then the purchase, then its supplier.
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
            // Built in this return's own branch. A plain `Purchase::factory()`
            // would build its own branch, and therefore its own company.
            'purchase_id' => fn (array $attributes) => Purchase::factory()
                ->create(['branch_id' => $attributes['branch_id']])
                ->getKey(),
            // The supplier of the purchase being returned — not a new one, which
            // is what `Ledger::factory()` gave: a return to a supplier who had
            // never sold anything, in a company of their own.
            'supplier_id' => fn (array $attributes) => Purchase::withoutGlobalScopes()
                ->whereKey($attributes['purchase_id'])
                ->value('supplier_id'),
            'number' => fake()->unique()->numberBetween(1, 999999),
            'date' => fake()->date(),
            'reason' => fake()->randomElement(PurchaseReturnReason::values()),
            'description' => fake()->optional()->sentence(),
            'status' => TransactionStatus::POSTED->value,
        ];
    }
}

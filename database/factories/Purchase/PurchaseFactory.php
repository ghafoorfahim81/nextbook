<?php

namespace Database\Factories\Purchase;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Enums\DiscountType;
use App\Enums\SalePurchaseType;
use App\Enums\TransactionStatus;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use App\Models\Purchase\Purchase;
use App\Models\Administration\Branch;

class PurchaseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Purchase::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            // Branch, then company, then anything that belongs to the company:
            // Laravel expands a definition in order and hands each closure what
            // has already resolved.
            'branch_id' => Branch::factory(),
            // Read off the branch rather than built separately: a document's
            // company and its branch's company have to agree, and the composite
            // foreign key enforces that.
            'company_id' => fn (array $attributes) => Branch::withoutGlobalScopes()
                ->whereKey($attributes['branch_id'])
                ->value('company_id'),
            // Plain `Ledger::factory()` and `Account::factory()` calls fall back
            // to a company of their own, which gave the purchase a supplier and
            // a bank account out of two other companies' books.
            'supplier_id' => fn (array $attributes) => Ledger::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            'bank_account_id' => fn (array $attributes) => Account::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            'number' => fake()->unique()->numberBetween(1, 999999),
            'date' => fake()->date(),
            'discount' => fake()->randomFloat(2, 0, 30),
            'discount_type' => DiscountType::PERCENTAGE->value,
            'type' => SalePurchaseType::Cash->value,
            'description' => fake()->optional()->sentence(),
            'status' => TransactionStatus::POSTED->value,
        ];
    }
}

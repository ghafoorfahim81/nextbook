<?php

namespace Database\Factories\Account;

use App\Models\Administration\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Administration\Branch;

class AccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Account::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            // Declared before the account type, which is built in this company.
            // Laravel expands a definition in order and hands each closure the
            // attributes already resolved, including the one a caller passed in.
            // A plain `AccountType::factory()` falls back to a company of its
            // own, so the account was classified by a type from other books.
            'company_id' => Company::factory(),
            'account_type_id' => fn (array $attributes) => AccountType::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            'name' => fake()->unique()->company().' Account',
            'local_name' => null,
            'number' => (string) fake()->unique()->numberBetween(1000, 99999),
            'slug' => null,
            'is_active' => true,
            'is_main' => false,
            'remark' => fake()->optional()->sentence(),
        ];
    }
}

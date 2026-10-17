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
            'name' => fake()->unique()->company().' Account',
            'local_name' => null,
            'number' => (string) fake()->unique()->numberBetween(1000, 99999),
            'account_type_id' => AccountType::factory(),
            'slug' => null,
            'is_active' => true,
            'is_main' => false,
            'company_id' => Company::factory(),
            'remark' => fake()->optional()->sentence(),
        ];
    }
}

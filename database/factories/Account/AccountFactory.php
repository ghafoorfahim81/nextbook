<?php

namespace Database\Factories\Account;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Account\Tenant;
use App\Models\Administration\Branch;
use App\Models\User;

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
            'name' => fake()->unique()->word(),
            'number' => 'Ac-'.$this->faker->unique()->randomNumber(),
            'account_type_id' => AccountType::factory(),
            'is_active' => $this->faker->boolean(),
            'branch_id' => Branch::factory(),
            'remark' => $this->faker->text(),
            'created_by' => User::factory(),
        ];
    }
}

<?php

namespace Database\Factories\Account;

use App\Models\Administration\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Account\AccountType;

class AccountTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AccountType::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => fake()->unique()->slug(2),
            'nature' => fake()->randomElement(['asset', 'liability', 'equity', 'income', 'expense', 'non-posting']),
            'remark' => fake()->optional()->sentence(),
            'branch_id' => Branch::factory(),
            'is_main' => false,
        ];
    }
}

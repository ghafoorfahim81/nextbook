<?php

namespace Database\Factories\Administration;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Administration\Branch;
use App\Models\Administration\Company;

class BranchFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->company(),
            'is_main' => false,
            'remark' => fake()->optional()->sentence(),
            'location' => fake()->optional()->city(),
            'parent_id' => null,
            'created_by' => User::factory()->create()->id,
            'updated_by' => null,
        ];
    }
}

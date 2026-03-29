<?php

namespace Database\Factories\Administration;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Administration\Branch;

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
            'name' => fake()->unique()->company(),
            'is_main' => false,
            'sub_domain' => fake()->optional()->domainWord(),
            'remark' => fake()->optional()->sentence(),
            'location' => fake()->optional()->city(),
            'parent_id' => null,
        ];
    }
}

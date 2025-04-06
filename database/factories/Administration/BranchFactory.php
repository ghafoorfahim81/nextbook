<?php

namespace Database\Factories\Administration;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Branch;
use App\Models\User;

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
            'id' => Str::ulid(), // Generate a UUID for the department ID
            'name' => $this->faker->name(),
            'is_main' => $this->faker->boolean(),
            'sub_domain' => $this->faker->domainName(),
            'remark' => $this->faker->text(),
            'parent_id' => null,

        ];
    }
}

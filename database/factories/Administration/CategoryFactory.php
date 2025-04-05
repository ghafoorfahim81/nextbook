<?php

namespace Database\Factories\Administration;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Category;

class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'id' => Str::ulid(), // Generate a UUID for the department ID
            'name' => $this->faker->name(),
            'remark' => $this->faker->text(),
            'parent_id' => null,
        ];
    }
}

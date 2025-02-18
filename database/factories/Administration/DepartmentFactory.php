<?php

namespace Database\Factories\Administration;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Department;
use App\Models\User;

class DepartmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Department::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid(), // Generate a UUID for the department ID
            'name' => $this->faker->name(),
            'code' => $this->faker->unique()->randomNumber(),
            'remark' => $this->faker->text(),
        ];
    }
}

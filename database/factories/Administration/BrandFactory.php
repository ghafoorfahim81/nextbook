<?php

namespace Database\Factories\Administration;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Administration\Branch;
use App\Models\Administration\Brand;

class BrandFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Brand::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'legal_name' => fake()->optional()->company(),
            'registration_number' => fake()->optional()->bothify('REG-#####'),
            'logo' => null,
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'website' => fake()->optional()->url(),
            'industry' => fake()->optional()->word(),
            'type' => fake()->optional()->word(),
            'address' => fake()->optional()->address(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'branch_id' => Branch::factory(),
        ];
    }
}

<?php

namespace Database\Factories\Administration;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Branch;
use App\Models\Administration\Brand;
use App\Models\User;

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
            'name' => fake()->name(),
            'legal_name' => fake()->word(),
            'registration_number' => fake()->word(),
            'logo' => fake()->word(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'website' => fake()->word(),
            'industry' => fake()->word(),
            'type' => fake()->word(),
            'address' => fake()->word(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'branch_id' => Branch::factory(),
            'created_by' => User::factory()->create()->created_by,
            'updated_by' => User::factory()->create()->updated_by,
        ];
    }
}

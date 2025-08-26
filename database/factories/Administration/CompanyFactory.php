<?php

namespace Database\Factories\Administration;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Company;
use App\Models\User;

class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name_en' => fake()->word(),
            'name_fa' => fake()->word(),
            'name_pa' => fake()->word(),
            'abbreviation' => fake()->word(),
            'address' => fake()->word(),
            'phone' => fake()->phoneNumber(),
            'country' => fake()->country(),
            'city' => fake()->city(),
            'logo' => fake()->word(),
            'calendar_type' => fake()->word(),
            'working_style' => fake()->word(),
            'business_type' => fake()->word(),
            'locale' => fake()->word(),
            'currency' => fake()->word(),
            'email' => fake()->safeEmail(),
            'website' => fake()->word(),
            'invoice_description' => fake()->text(),
            'created_by' => User::factory()->create()->created_by,
            'updated_by' => User::factory()->create()->updated_by,
        ];
    }
}

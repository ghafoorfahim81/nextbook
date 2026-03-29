<?php

namespace Database\Factories\Administration;

use App\Enums\BusinessType;
use App\Enums\CalendarType;
use App\Enums\CostingMethod;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use App\Models\Administration\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Administration\Company;

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
            'name_en' => fake()->company(),
            'name_fa' => fake()->word(),
            'name_pa' => fake()->word(),
            'abbreviation' => strtoupper(fake()->lexify('???')),
            'address' => fake()->optional()->address(),
            'phone' => fake()->optional()->phoneNumber(),
            'country' => fake()->country(),
            'city' => fake()->city(),
            'logo' => null,
            'calendar_type' => CalendarType::JALALI->value,
            'working_style' => WorkingStyle::NORMAL->value,
            'business_type' => BusinessType::PHARMACY_SHOP->value,
            'locale' => Locale::EN->value,
            'currency_id' => Currency::factory(),
            'costing_method' => CostingMethod::FIFO->value,
            'email' => fake()->optional()->safeEmail(),
            'website' => fake()->optional()->url(),
            'invoice_description' => fake()->optional()->sentence(),
        ];
    }
}

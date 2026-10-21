<?php

namespace Database\Factories\Ledger;

use App\Models\Administration\Company;
use App\Models\Administration\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Administration\Currency;
use App\Models\Ledger\Ledger;
use App\Enums\LedgerType;
class LedgerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Ledger::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->name(),
            'code' => strtoupper(fake()->unique()->bothify('L##??')),
            'address' => fake()->optional()->address(),
            'contact_person' => fake()->optional()->name(),
            'phone_no' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            // Company before currency: Laravel expands a definition in order, so
            // the currency can only be built in this company if it is already
            // resolved. A plain `Currency::factory()` falls back to a company of
            // its own, giving the ledger a currency from other books.
            'company_id' => Company::factory(),
            'currency_id' => fn (array $attributes) => Currency::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            // Commercial types only. Employee ledgers are created by
            // EmployeeObserver and are meaningless without the employee behind
            // them, so randomising across all values would mint orphans.
            'type' => fake()->randomElement(LedgerType::commercialValues()),
            'is_main' => false,
            'is_active' => true,
        ];
    }
}

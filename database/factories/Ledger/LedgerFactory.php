<?php

namespace Database\Factories\Ledger;

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
            'currency_id' => Currency::factory(),
            'branch_id' => Branch::factory(),
            'type' => fake()->randomElement(LedgerType::values()),
            'is_main' => false,
            'is_active' => true,
        ];
    }
}

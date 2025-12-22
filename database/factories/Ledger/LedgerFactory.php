<?php

namespace Database\Factories\Ledger;

use App\Models\Administration\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Currency;
use App\Models\Ledger\Ledger;
use App\Models\User;
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
        $currency_id = Currency::first()->id;
        return [
            'name' => fake()->name(),
            'code' => fake()->word(),
            'address' => fake()->word(),
            'contact_person' => fake()->name(),
            'phone_no' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'currency_id' => $currency_id,
            'branch_id' => Branch::factory(),
            'type' => fake()->randomElement(LedgerType::cases()),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}

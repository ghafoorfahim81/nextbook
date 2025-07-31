<?php

namespace Database\Factories\LedgerOpening;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\LedgerOpening\LedgerOpening;
use App\Models\User;

class LedgerOpeningFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LedgerOpening::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'transactionable' => fake()->word(),
            'ledgerable' => fake()->word(),
            'created_by' => User::factory()->create()->created_by,
            'updated_by' => User::factory()->create()->updated_by,
        ];
    }
}

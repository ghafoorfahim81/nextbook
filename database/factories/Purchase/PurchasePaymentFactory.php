<?php

namespace Database\Factories\Purchase;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchasePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'purchase_id' => fake()->word(),
            'payment_id' => fake()->word(),
            'amount' => fake()->randomFloat(0, 0, 9999999999.),
            'created_by' => User::factory()->create()->created_by,
            'updated_by' => User::factory()->create()->updated_by,
            'deleted_by' => User::factory()->create()->deleted_by,
        ];
    }
}

<?php

namespace Database\Factories\Purchase;

use App\Models\Payment\Payment;
use App\Models\Purchase\Purchase;
use App\Models\Administration\Branch;
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
            'purchase_id' => Purchase::factory(),
            'payment_id' => Payment::factory(),
            'amount' => fake()->randomFloat(2, 0, 9999999999),
            'branch_id' => Branch::factory(),
            'created_by' => User::factory()->create()->id,
            'updated_by' => null,
            'deleted_by' => null,
        ];
    }
}

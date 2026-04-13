<?php

namespace Database\Factories\Sale;

use App\Models\Administration\Branch;
use App\Models\Receipt\Receipt;
use App\Models\Sale\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleReceiveFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'receipt_id' => Receipt::factory(),
            'amount' => fake()->randomFloat(2, 0, 9999999999),
            'branch_id' => Branch::factory(),
            'created_by' => User::factory()->create()->id,
            'updated_by' => null,
            'deleted_by' => null,
        ];
    }
}

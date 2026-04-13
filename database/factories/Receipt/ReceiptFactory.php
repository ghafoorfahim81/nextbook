<?php

namespace Database\Factories\Receipt;

use App\Enums\PaymentMode;
use App\Models\Administration\Branch;
use App\Models\Ledger\Ledger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1, 999999),
            'date' => fake()->date(),
            'ledger_id' => Ledger::factory(),
            'payment_mode' => PaymentMode::OnAccount->value,
            'cheque_no' => fake()->optional()->bothify('CHK-#####'),
            'narration' => fake()->optional()->sentence(),
            'branch_id' => Branch::factory(),
            'created_by' => User::factory()->create()->id,
            'updated_by' => null,
        ];
    }
}

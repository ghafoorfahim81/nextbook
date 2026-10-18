<?php

namespace Database\Factories\Payment;

use App\Enums\PaymentMode;
use App\Models\Administration\Branch;
use App\Models\Ledger\Ledger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
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
            // Read off the branch rather than built separately: a document's
            // company and its branch's company have to agree, and the composite
            // foreign key enforces that.
            'company_id' => fn (array $attributes) => Branch::withoutGlobalScopes()
                ->whereKey($attributes['branch_id'])
                ->value('company_id'),
            'created_by' => User::factory()->create()->id,
            'updated_by' => null,
        ];
    }
}

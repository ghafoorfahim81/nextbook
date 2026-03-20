<?php

namespace Database\Factories\Transaction;

use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionLineFactory extends Factory
{
    protected $model = TransactionLine::class;

    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'account_id' => Account::factory(),
            'ledger_id' => null,
            'journal_class_id' => null,
            'debit' => fake()->randomFloat(2, 1, 1000),
            'credit' => 0,
            'remark' => fake()->optional()->sentence(),
        ];
    }

    public function credit(float $amount = 100): static
    {
        return $this->state(fn () => [
            'debit' => 0,
            'credit' => $amount,
        ]);
    }

    public function withLedger(?string $ledgerId = null): static
    {
        return $this->state(fn () => [
            'ledger_id' => $ledgerId ?? Ledger::factory(),
        ]);
    }
}

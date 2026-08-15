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
            // A line defaults to its header's currency and rate — the
            // single-currency voucher. Tests that need a line at a different
            // rate override these explicitly.
            'currency_id' => fn (array $attributes) => $this->header($attributes)?->currency_id,
            'rate' => fn (array $attributes) => $this->header($attributes)?->rate ?? 1,
            'debit' => fake()->randomFloat(2, 1, 1000),
            'credit' => 0,
            'base_debit' => fn (array $attributes) => $this->toBase($attributes, 'debit'),
            'base_credit' => fn (array $attributes) => $this->toBase($attributes, 'credit'),
            'remark' => fake()->optional()->sentence(),
        ];
    }

    private function header(array $attributes): ?Transaction
    {
        return Transaction::withoutGlobalScopes()->find($attributes['transaction_id'] ?? null);
    }

    private function toBase(array $attributes, string $column): float
    {
        $rate = (float) ($attributes['rate'] ?? $this->header($attributes)?->rate ?? 1);

        return round((float) ($attributes[$column] ?? 0) * $rate, 4);
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

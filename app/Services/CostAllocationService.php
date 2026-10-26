<?php

namespace App\Services;

use App\Models\Accounting\AllocationRule;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

/**
 * Splits a shared cost across branches by an allocation rule (phase 7).
 *
 * The split is made when the expense posts, with the shares in force on the
 * expense's date, so the ledger itself carries each branch's part and no
 * report has to redo the arithmetic. Each part is rounded to the cent and the
 * last branch takes what rounding left over, so the parts always add up to
 * the amount exactly: 100 split three ways is 33.33 + 33.33 + 33.34.
 *
 * Only the document-currency amount is split. The caller posts one debit and
 * one credit per part, and TransactionService values each in base currency,
 * so every pair balances in base too, to the last decimal.
 */
class CostAllocationService
{
    /**
     * @return list<array{branch_id: string, amount: string}>
     */
    public function split(AllocationRule $rule, string $date, string $amount): array
    {
        $shares = $rule->sharesOn($date);

        if ($shares->isEmpty()) {
            throw ValidationException::withMessages([
                'allocation_rule_id' => [__('messages.allocation.no_version_on_date', ['date' => $date])],
            ]);
        }

        $amount = Decimal::amount($amount);
        $left = $amount;
        $last = $shares->count() - 1;
        $parts = [];

        foreach ($shares->values() as $index => $share) {
            $part = $index === $last
                ? $left
                : Decimal::roundHalfUp(bcmul($amount, bcdiv((string) $share->percentage, '100', 10), 10), 2);

            $left = Decimal::sub($left, $part);
            $parts[] = ['branch_id' => (string) $share->branch_id, 'amount' => Decimal::amount($part)];
        }

        return $parts;
    }
}

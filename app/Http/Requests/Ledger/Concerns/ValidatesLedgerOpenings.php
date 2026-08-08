<?php

namespace App\Http\Requests\Ledger\Concerns;

use Illuminate\Contracts\Validation\Validator;

/**
 * Shared rules for the multi-currency opening balance grid used by the customer
 * and supplier forms.
 *
 * The form renders one row per currency, so most rows arrive blank. They are kept
 * in the payload rather than stripped before validation: dropping them would
 * renumber the rows, and an error on `openings.4.rate` would then point at a
 * different currency than the one the user typed into. Blank rows are instead
 * skipped by the row-level checks and discarded later by LedgerOpeningService.
 */
trait ValidatesLedgerOpenings
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function openingRules(): array
    {
        return [
            'openings' => ['nullable', 'array'],
            'openings.*.currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'openings.*.rate' => ['nullable', 'numeric'],
            'openings.*.amount' => ['nullable', 'numeric', 'min:0'],
            'openings.*.remark' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $seenCurrencies = [];

            foreach ((array) $this->input('openings', []) as $index => $row) {
                if (! is_array($row) || (float) ($row['amount'] ?? 0) <= 0) {
                    continue;
                }

                $currencyId = $row['currency_id'] ?? null;

                if (blank($currencyId)) {
                    $validator->errors()->add(
                        "openings.{$index}.currency_id",
                        __('validation.required', ['attribute' => __('general.currency')])
                    );
                } elseif (in_array($currencyId, $seenCurrencies, true)) {
                    // Two rows in one currency would silently double the balance.
                    $validator->errors()->add(
                        "openings.{$index}.currency_id",
                        __('validation.distinct', ['attribute' => __('general.currency')])
                    );
                } else {
                    $seenCurrencies[] = $currencyId;
                }

                // Reports value every line as `line * transactions.rate`, so a zero
                // rate would book the whole opening at nothing.
                if ((float) ($row['rate'] ?? 0) <= 0) {
                    $validator->errors()->add(
                        "openings.{$index}.rate",
                        __('validation.gt.numeric', ['attribute' => __('general.rate'), 'value' => 0])
                    );
                }
            }
        });
    }
}

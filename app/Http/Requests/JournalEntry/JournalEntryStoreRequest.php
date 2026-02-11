<?php

namespace App\Http\Requests\JournalEntry;

use Illuminate\Foundation\Http\FormRequest;

class JournalEntryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'rate' => ['required', 'numeric', 'min:1'],
            'remarks' => ['nullable', 'string'],

            'lines' => ['required', 'array', 'min:1'],

            'lines.*.account_id' => ['required', 'exists:accounts,id'],

            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],

            'lines.*.remark' => ['nullable', 'string'],
            'lines.*.ledger_id' => ['nullable', 'exists:ledgers,id'],
            'lines.*.bill_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($this->lines as $index => $line) {

                $debit = floatval($line['debit'] ?? 0);
                $credit = floatval($line['credit'] ?? 0);

                // Rule 1: Either debit OR credit (not both, not none)
                if (
                    ($debit > 0 && $credit > 0) ||
                    ($debit == 0 && $credit == 0)
                ) {
                    $validator->errors()->add(
                        "lines.$index.debit",
                        "Each line must contain either debit or credit, not both or neither."
                    );
                }

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            // Rule 2: Journal must be balanced
            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                $validator->errors()->add(
                    'lines',
                    'Total debit must equal total credit.'
                );
            }
        });
    }
}

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
            'number' => ['nullable', 'integer', 'max:255'],
            'date' => ['required', 'date'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'rate' => ['required', 'numeric', 'min:1'],
            'remarks' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
            'lines.*.debit' => ['required', 'numeric', 'min:0.01'],
            'lines.*.credit' => ['required', 'numeric', 'min:0.01'],
            'lines.*.remark' => ['nullable', 'string'],
            'lines.*.ledger_id' => ['required', 'exists:ledgers,id'],
            'lines.*.bill_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}



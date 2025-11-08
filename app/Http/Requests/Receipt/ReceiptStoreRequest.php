<?php

namespace App\Http\Requests\Receipt;

use Illuminate\Foundation\Http\FormRequest;

class ReceiptStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'ledger_id' => ['required', 'exists:ledgers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank_account_id' => ['required', 'exists:accounts,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'rate' => ['required', 'numeric', 'min:0'],
            'cheque_no' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
        ];
    }
}



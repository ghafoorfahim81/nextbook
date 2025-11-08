<?php

namespace App\Http\Requests\Receipt;

use Illuminate\Foundation\Http\FormRequest;

class ReceiptUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['sometimes', 'required', 'integer', 'max:255'],
            'date' => ['sometimes', 'required', 'date'],
            'ledger_id' => ['sometimes', 'required', 'exists:ledgers,id'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'bank_account_id' => ['sometimes', 'required', 'exists:accounts,id'],
            'currency_id' => ['sometimes', 'required', 'exists:currencies,id'],
            'rate' => ['sometimes', 'required', 'numeric', 'min:0'],
            'cheque_no' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
        ];
    }
}



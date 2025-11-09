<?php

namespace App\Http\Requests\AccountTransfer;

use Illuminate\Foundation\Http\FormRequest;

class AccountTransferUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['nullable', 'max:255'],
            'date' => ['nullable', 'date'],
            'from_account_id' => ['nullable', 'exists:accounts,id'],
            'to_account_id' => ['nullable', 'different:from_account_id', 'exists:accounts,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string'],
        ];
    }
}



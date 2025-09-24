<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class TransactionStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'transactionable' => ['required', 'string'],
            'amount' => ['required', 'numeric'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'rate' => ['required', 'numeric'],
            'date' => ['required', 'date'],
            'ledger_id' => ['nullable', 'integer', 'exists:ledgers,id'],
            'type' => ['required', 'string'],
            'remark' => ['nullable', 'string'],
            'created_by' => ['required'],
            'updated_by' => ['nullable'],
        ];
    }
}

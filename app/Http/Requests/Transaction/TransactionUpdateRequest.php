<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class TransactionUpdateRequest extends FormRequest
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
            'reference_type' => ['nullable', 'string'],
            'reference_id' => ['nullable', 'string'],
            'amount' => ['required', 'numeric'],
            'account_id' => ['required', 'string', 'exists:accounts,id'],
            'currency_id' => ['required', 'string', 'exists:currencies,id'],
            'rate' => ['required', 'numeric'],
            'date' => ['required', 'date'],
            'type' => ['required', 'string'],
            'remark' => ['nullable', 'string'],
        ];
    }
}

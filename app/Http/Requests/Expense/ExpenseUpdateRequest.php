<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'required', 'date'],
            'remarks' => ['nullable', 'string'],
            'category_id' => ['sometimes', 'required', 'string', 'exists:expense_categories,id'],
            'expense_account_id' => ['sometimes', 'required', 'string', 'exists:accounts,id'],
            'bank_account_id' => ['sometimes', 'required', 'string', 'exists:accounts,id'],
            'currency_id' => ['sometimes', 'required', 'string', 'exists:currencies,id'],
            'rate' => ['sometimes', 'required', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'file', 'max:10240'],
            'details' => ['sometimes', 'required', 'array', 'min:1'],
            'details.*.amount' => ['required', 'numeric', 'min:0.01'],
            'details.*.title' => ['required', 'string', 'max:255'], 
        ];
    }
    
}


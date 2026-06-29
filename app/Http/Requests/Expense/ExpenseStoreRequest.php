<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['nullable', 'string', 'max:50'],
            'date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'category_id' => ['required', 'string', 'exists:expense_categories,id'],
            'expense_account_id' => ['required', 'string', 'exists:accounts,id'],
            'bank_account_id' => ['required', 'string', 'exists:accounts,id'],
            'currency_id' => ['required', 'string', 'exists:currencies,id'],
            'rate' => ['required', 'numeric', 'min:0'], 
            'details' => ['required', 'array', 'min:1'],
            'details.*.amount' => ['required', 'numeric', 'min:0.01'],
            'details.*.title' => ['required', 'string', 'max:255'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'details.required' => 'At least one expense detail is required.',
            'details.min' => 'At least one expense detail is required.',
            'details.*.amount.required' => 'Amount is required for each detail.',
            'details.*.amount.min' => 'Amount must be greater than 0.',
            'details.*.title.required' => 'Title is required for each detail.',
        ];
    }
}


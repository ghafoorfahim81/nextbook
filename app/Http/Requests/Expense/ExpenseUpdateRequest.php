<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Concerns\BranchScopedUnique;

class ExpenseUpdateRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['nullable', 'string', 'max:50', $this->uniqueInCompany('expenses', $this->route('expense'), 'number')],
            'date' => ['sometimes', 'required', 'date'],
            'remarks' => ['nullable', 'string'],
            'category_id' => ['sometimes', 'required', 'string', 'exists:expense_categories,id'],
            // Phase 7: optional dimensions.
            'cost_center_id' => ['nullable', 'string', 'exists:cost_centers,id'],
            'allocation_rule_id' => ['nullable', 'string', 'exists:allocation_rules,id'],
            'expense_account_id' => ['sometimes', 'required', 'string', 'exists:accounts,id'],
            'bank_account_id' => ['sometimes', 'required', 'string', 'exists:accounts,id'],
            'currency_id' => ['sometimes', 'required', 'string', 'exists:currencies,id'],
            'rate' => ['sometimes', 'required', 'numeric', 'min:0'], 
            'details' => ['sometimes', 'required', 'array', 'min:1'],
            'details.*.amount' => ['required', 'numeric', 'min:0.01'],
            'details.*.title' => ['required', 'string', 'max:255'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
    
}


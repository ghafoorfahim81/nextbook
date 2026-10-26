<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Concerns\BranchScopedUnique;

class ExpenseStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['nullable', 'string', 'max:50', $this->uniqueInCompany('expenses', column: 'number')],
            'date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'category_id' => ['required', 'string', 'exists:expense_categories,id'],
            // Phase 7: optional dimensions.
            'cost_center_id' => ['nullable', 'string', 'exists:cost_centers,id'],
            'allocation_rule_id' => ['nullable', 'string', 'exists:allocation_rules,id'],
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
            'details.required' => __('messages.validation.expense_detail_required'),
            'details.min' => __('messages.validation.expense_detail_required'),
            'details.*.amount.required' => __('messages.validation.expense_detail_amount_required'),
            'details.*.amount.min' => __('messages.validation.expense_detail_amount_min'),
            'details.*.title.required' => __('messages.validation.expense_detail_title_required'),
        ];
    }
}


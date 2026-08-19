<?php

namespace App\Http\Requests\Expense;

use App\Http\Requests\Concerns\BranchScopedUnique;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseCategoryStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueInBranch('expense_categories')],
            'remarks' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}


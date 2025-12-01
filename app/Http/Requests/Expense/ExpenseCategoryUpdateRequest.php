<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('expense_categories')->ignore($this->route('expense_category'))->whereNull('deleted_at')],
            'remarks' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}


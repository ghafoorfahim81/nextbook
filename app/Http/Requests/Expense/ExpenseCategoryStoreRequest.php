<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseCategoryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'remarks' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}


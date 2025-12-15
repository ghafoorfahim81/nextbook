<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class ChartOfAccountUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:accounts,name'],
            'number' => ['required', 'string', 'unique:accounts,number'],
            'account_type_id' => ['required', 'string', 'exists:account_types,id'],
            'slug' => ['nullable', 'string', 'unique:accounts,slug'],
            'remark' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_main' => ['nullable', 'boolean'],
            'openings' => ['nullable', 'array'],
            'openings.*.currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'openings.*.amount' => ['nullable', 'numeric'],
            'openings.*.rate' => ['required', 'numeric'],
            'openings.*.type' => ['nullable', 'string', 'in:debit,credit'],
        ];
    }
}

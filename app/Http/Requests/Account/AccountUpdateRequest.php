<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountUpdateRequest extends FormRequest
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
        // The route-model-bound account instance (parameter name: chart_of_account)
        $chartOfAccount = $this->route('chart_of_account');

        return [
            'name' => [
                'required',
                'string',
                Rule::unique('accounts', 'name')->ignore($chartOfAccount),
            ],
            'number' => [
                'required',
                'string',
                Rule::unique('accounts', 'number')->ignore($chartOfAccount),
            ],
            'account_type_id' => ['required', 'string', 'exists:account_types,id'],
            'slug' => [
                'nullable',
                'string',
                Rule::unique('accounts', 'slug')->ignore($chartOfAccount),
            ],
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

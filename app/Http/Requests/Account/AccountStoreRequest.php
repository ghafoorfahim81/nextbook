<?php

namespace App\Http\Requests\Account;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:accounts,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'number' => ['required', 'string', 'unique:accounts,number,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'account_type_id' => ['required', 'string', 'exists:account_types,id'],
            'is_active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],
            'slug' => ['nullable', 'string'],
            'openings' => ['nullable', 'array'],
            'openings.*.currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'openings.*.amount' => ['nullable', 'numeric'],
            'openings.*.rate' => ['required', 'numeric'],
            'openings.*.type' => ['nullable', 'string', Rule::in(TransactionType::values())],
        ];
    }
}

<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

class CurrencyStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:currencies,name'],
            'code' => ['required', 'string', 'unique:currencies,code'],
            'symbol' => ['required', 'string', 'unique:currencies,symbol'],
            'format' => ['required', 'string', 'unique:currencies,format'],
            'exchange_rate' => ['required', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'flag' => ['nullable', 'string', 'unique:currencies,flag'],
            'branch_id' => ['required', 'string', 'exists:branches,id'],
        ];
    }
}

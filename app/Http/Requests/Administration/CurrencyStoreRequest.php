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
            'name' => ['required', 'string', 'unique:currencies,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'code' => ['required', 'string', 'unique:currencies,code,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'symbol' => ['required', 'string', 'unique:currencies,symbol,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'format' => ['required', 'string', 'unique:currencies,format,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'exchange_rate' => ['required', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'is_base_currency' => ['nullable', 'boolean'],
            'flag' => ['nullable', 'string', 'unique:currencies,flag,NULL,id,deleted_at,NULL'],
        ];
    }
}

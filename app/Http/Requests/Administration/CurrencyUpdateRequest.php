<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

class CurrencyUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:256', \Illuminate\Validation\Rule::unique('currencies')->ignore($this->route('currency'))],
            'code' => ['required', 'string', 'max:256', \Illuminate\Validation\Rule::unique('currencies')->ignore($this->route('currency'))],
            'symbol' => ['required', 'string', 'max:256', \Illuminate\Validation\Rule::unique('currencies')->ignore($this->route('currency'))],
            'format' => ['nullable', 'string', 'max:256', \Illuminate\Validation\Rule::unique('currencies')->ignore($this->route('currency'))],
            'exchange_rate' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'flag' => ['nullable', 'string', 'max:256', \Illuminate\Validation\Rule::unique('currencies')->ignore($this->route('currency'))],
            'branch_id' => ['required', 'string', 'exists:branches,id'],
        ];
    }
}

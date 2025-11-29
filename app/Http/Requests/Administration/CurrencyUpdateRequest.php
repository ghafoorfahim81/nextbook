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
            'name' => ['required', 'string', 'max:256', \Illuminate\Validation\Rule::unique('currencies')->ignore($this->route('currency'))->whereNull('deleted_at')],
            'code' => ['required', 'string', 'max:256', \Illuminate\Validation\Rule::unique('currencies')->ignore($this->route('currency'))->whereNull('deleted_at')],
            'symbol' => ['required', 'string', 'max:256', \Illuminate\Validation\Rule::unique('currencies')->ignore($this->route('currency'))->whereNull('deleted_at')],
            'format' => ['nullable', 'string', 'max:256', \Illuminate\Validation\Rule::unique('currencies')->ignore($this->route('currency'))->whereNull('deleted_at')],
            'exchange_rate' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'is_base_currency' => ['nullable', 'boolean'],
            'flag' => ['nullable', 'string', 'max:256', \Illuminate\Validation\Rule::unique('currencies')->ignore($this->route('currency'))->whereNull('deleted_at')],
        ];
    }
}

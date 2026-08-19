<?php

namespace App\Http\Requests\Administration;

use App\Models\Administration\Currency;
use App\Http\Requests\Concerns\BranchScopedUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CurrencyStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    protected function prepareForValidation(): void
    {
        $currencyCode = $this->input('currency_code');

        if (!is_string($currencyCode) || !array_key_exists($currencyCode, Currency::currencyList())) {
            return;
        }

        $this->merge(Currency::attributesFromListCode($currencyCode));
    }

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
            'currency_code' => ['required', 'string', Rule::in(array_keys(Currency::currencyList()))],
            'name' => ['required', 'string', $this->uniqueInBranch('currencies')],
            'code' => ['required', 'string', $this->uniqueInBranch('currencies')],
            'symbol' => ['required', 'string'],
            'format' => ['required', 'string'],
            'exchange_rate' => ['required', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'is_base_currency' => ['nullable', 'boolean'],
            'flag' => ['nullable', 'string', 'unique:currencies,flag,NULL,id,deleted_at,NULL'],
        ];
    }

    public function currencyData(): array
    {
        return Arr::except($this->validated(), ['currency_code']);
    }
}

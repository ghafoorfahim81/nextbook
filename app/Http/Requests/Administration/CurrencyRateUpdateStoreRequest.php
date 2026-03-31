<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

class CurrencyRateUpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('currencies.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'updates' => ['required', 'array', 'min:1'],
            'updates.*.currency_id' => ['required', 'string', 'distinct', 'exists:currencies,id'],
            'updates.*.exchange_rate' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'updates.*.exchange_rate' => __('admin.currency.exchange_rate'),
        ];
    }
}

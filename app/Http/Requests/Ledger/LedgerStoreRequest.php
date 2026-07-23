<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;

class LedgerStoreRequest extends FormRequest
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
            'name' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string'],
            'phone_no' => ['nullable', 'digits_between:1,10'],
            'email' => ['nullable', 'email'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'group_id' => ['nullable', 'string', 'exists:customer_groups,id'],
            'payment_term_id' => ['nullable', 'string', 'exists:payment_terms,id'],
            'country_id' => ['nullable', 'string', 'exists:countries,id'],
            'province_id' => ['nullable', 'string', 'exists:provinces,id'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'credit_limit_status' => ['nullable', 'in:Block,Indicate'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'whatsapp_number' => ['nullable', 'digits_between:1,10'],
            'rate' => ['nullable', 'numeric'],
            'amount' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

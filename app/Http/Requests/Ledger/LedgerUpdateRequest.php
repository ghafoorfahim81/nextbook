<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;

class LedgerUpdateRequest extends FormRequest
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
            'credit_limit_enabled' => ['nullable', 'boolean'],
            'credit_terms' => ['nullable', 'in:strict,warning,flexible'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:10240'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'whatsapp_number' => ['nullable', 'digits_between:1,10'],
            'rate' => ['nullable', 'numeric'],
            'opening_currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric','required_with:opening_currency_id'],
            'amount' => ['nullable', 'numeric'],
            'remark' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Ledger;

use App\Enums\CreditTerms;
use App\Http\Requests\Ledger\Concerns\ValidatesLedgerOpenings;
use App\Http\Requests\Ledger\Concerns\ValidatesPhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LedgerStoreRequest extends FormRequest
{
    use ValidatesLedgerOpenings, ValidatesPhoneNumbers;

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
            'code' => [
                'nullable',
                'string',
                Rule::unique('ledgers', 'code')
                    ->where(fn ($query) => $query
                        ->where('branch_id', $this->user()?->branch_id)
                        ->whereNull('deleted_at')),
            ],
            'address' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string'],
            'phone_no' => $this->phoneRules(),
            'email' => ['nullable', 'email'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'group_id' => ['nullable', 'string', 'exists:customer_groups,id'],
            'payment_term_id' => ['nullable', 'string', 'exists:payment_terms,id'],
            'country_id' => ['nullable', 'string', 'exists:countries,id'],
            'province_id' => ['nullable', 'string', 'exists:provinces,id'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'credit_limit_enabled' => ['nullable', 'boolean'],
            'credit_terms' => ['nullable', Rule::enum(CreditTerms::class)],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'whatsapp_number' => $this->phoneRules(),
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:10240'],
            'is_active' => ['nullable', 'boolean'],
            ...$this->openingRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->phoneMessages();
    }
}

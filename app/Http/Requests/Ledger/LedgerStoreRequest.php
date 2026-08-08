<?php

namespace App\Http\Requests\Ledger;

use App\Http\Requests\Ledger\Concerns\ValidatesLedgerOpenings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LedgerStoreRequest extends FormRequest
{
    use ValidatesLedgerOpenings;

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
            'phone_no' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'is_active' => ['nullable', 'boolean'],
            ...$this->openingRules(),
        ];
    }
}

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
            'phone_no' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'branch_id' => ['required', 'string', 'exists:branches,id'],
            'type' => ['nullable', 'string'],
        ];
    }
}

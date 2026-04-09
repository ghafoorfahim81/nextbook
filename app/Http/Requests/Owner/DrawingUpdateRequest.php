<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class DrawingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_id' => ['required', 'string', 'exists:owners,id'],
            'bank_account_id' => ['required', 'string', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_id' => ['required', 'string', 'exists:currencies,id'],
            'rate' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'narration' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

<?php

namespace App\Http\Requests\LedgerOpening;

use Illuminate\Foundation\Http\FormRequest;

class LedgerOpeningStoreRequest extends FormRequest
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
            'transactionable' => ['required', 'string'],
            'ledgerable' => ['required', 'string'],
            'created_by' => ['required'],
            'updated_by' => ['nullable'],
        ];
    }
}

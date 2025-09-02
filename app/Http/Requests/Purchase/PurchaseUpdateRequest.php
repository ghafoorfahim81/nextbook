<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseUpdateRequest extends FormRequest
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
            'number' => ['required', 'string'],
            'supplier_id' => ['required', 'integer', 'exists:ledgers,id'],
            'date' => ['required', 'date'],
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'discount' => ['nullable', 'numeric'],
            'discount_type' => ['nullable', 'string'],
            'type' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ];
    }
}

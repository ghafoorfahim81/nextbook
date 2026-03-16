<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class PurchasePaymentStoreRequest extends FormRequest
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
            'purchase_id' => ['required', 'string'],
            'payment_id' => ['required', 'string'],
            'amount' => ['required', 'numeric'],
            'created_by' => ['required'],
            'updated_by' => ['nullable'],
            'deleted_by' => ['nullable'],
        ];
    }
}

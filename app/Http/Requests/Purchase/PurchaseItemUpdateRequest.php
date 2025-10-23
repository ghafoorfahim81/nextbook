<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseItemUpdateRequest extends FormRequest
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
            'purchase_id' => ['sometimes', 'required', 'string', 'exists:purchases,id'],
            'item_id' => ['sometimes', 'required', 'string', 'exists:items,id'],
            'batch' => ['nullable', 'string', 'max:255'],
            'expire_date' => ['nullable', 'date'],
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0'],
            'unit_measure_id' => ['nullable', 'string', 'exists:unit_measures,id'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'free' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

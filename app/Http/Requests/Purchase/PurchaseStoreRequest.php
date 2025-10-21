<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseStoreRequest extends FormRequest
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
            'number' => ['required'],
            'supplier_id' => ['required', 'string', 'exists:ledgers,id'],
            'date' => ['required', 'date'],
            'transaction_id' => ['required', 'string', 'exists:transactions,id'],
            'discount' => ['nullable', 'numeric'],
            'discount_type' => ['nullable', 'string'],
            'type' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.purchase_id' => ['required', 'string', 'exists:purchases,id'],
            'items.*.item_id' => ['required', 'string', 'exists:items,id'],
            'items.*.batch' => ['nullable', 'string'],
            'items.*.expire_date' => ['nullable', 'date'],
            'items.*.quantity' => ['required', 'numeric'],
            'items.*.unit_measure_id' => ['required', 'string', 'exists:unit_measures,id'],
            'items.*.price' => ['required', 'numeric'],
            'items.*.discount' => ['nullable', 'numeric'],
            'items.*.free' => ['nullable', 'numeric'],
            'items.*.tax' => ['nullable', 'numeric'],
        ];
    }
}

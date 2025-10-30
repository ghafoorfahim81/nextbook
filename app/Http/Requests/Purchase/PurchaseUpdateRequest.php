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
            'number' => ['required'],
            'supplier_id' => ['required', 'string', 'exists:ledgers,id'],
            'date' => ['required', 'date'],
            'store_id' => ['required', 'string', 'exists:stores,id'],
            'discount' => ['nullable', 'numeric'],
            'discount_type' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'sale_purchase_type_id' => ['nullable', 'string'],
            'transaction_total' => ['required', 'numeric'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric'],
            'item_list' => ['nullable', 'array'],
            'item_list.*.item_id' => ['required', 'string', 'exists:items,id'],
            'item_list.*.quantity' => ['required', 'numeric', 'min:0.0000001'],
            'item_list.*.unit_price' => ['required', 'numeric', 'min:0'],
            'item_list.*.free' => ['nullable', 'numeric', 'min:0'],
            'item_list.*.batch' => ['nullable', 'string'],
            'item_list.*.expire_date' => ['nullable', 'date'],
            'item_list.*.discount' => ['nullable', 'numeric', 'min:0'],
            'item_list.*.tax' => ['nullable', 'numeric', 'min:0'],
            'item_list.*.unit_measure_id' => ['required', 'string', 'exists:unit_measures,id'],
            'payment' => ['nullable', 'array'],
            'payment.account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'payment.amount' => ['nullable', 'numeric', 'min:0'],
            'payment.note' => ['nullable', 'string'],
        ];
    }
}

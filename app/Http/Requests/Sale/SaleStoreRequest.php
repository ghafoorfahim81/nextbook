<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class SaleStoreRequest extends FormRequest
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
            'number' => ['required', 'unique:sales,number'],
            'customer_id' => ['required', 'string', 'exists:ledgers,id'],
            'date' => ['required', 'date'],
            'transaction_total' => ['required', 'numeric'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric'],
            'sale_purchase_type_id' => ['nullable', 'string'],
            'payment' => ['nullable', 'array'],
            'payment.method' => ['nullable', 'string'],
            'payment.amount' => ['nullable', 'numeric'],
            'payment.account_id' => ['nullable', 'string'],
            'payment.note' => ['nullable', 'string'],
            'discount' => ['nullable', 'numeric'],
            'discount_type' => ['nullable', 'string', 'in:percentage,currency'],
            'store_id' => ['nullable', 'string', 'exists:stores,id'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'item_list' => ['required', 'array','min:1'],
            'item_list.*.item_id' => ['required', 'string', 'exists:items,id'],
            'item_list.*.batch' => ['nullable', 'string'],
            'item_list.*.expire_date' => ['nullable', 'date'],
            'item_list.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'item_list.*.unit_measure_id' => ['required', 'string', 'exists:unit_measures,id'],
            'item_list.*.unit_price' => ['required', 'numeric', 'min:0'],
            'item_list.*.item_discount' => ['nullable', 'numeric', 'min:0'],
            'item_list.*.free' => ['nullable', 'numeric', 'min:0'],
            'item_list.*.tax' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

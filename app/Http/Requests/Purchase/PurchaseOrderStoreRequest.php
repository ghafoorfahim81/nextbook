<?php

namespace App\Http\Requests\Purchase;

use App\Enums\DiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderStoreRequest extends FormRequest
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
            'number' => ['required', 'integer', 'min:1', 'unique:purchase_orders,number,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'supplier_id' => ['required', 'string', 'exists:ledgers,id'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'warehouse_id' => ['nullable', 'string', 'exists:warehouses,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', Rule::in(DiscountType::values())],
            'note' => ['nullable', 'string'],
            'item_list' => ['required', 'array', 'min:1'],
            'item_list.*.item_id' => ['required', 'string', 'exists:items,id'],
            'item_list.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'item_list.*.free' => ['nullable', 'numeric', 'min:0'],
            'item_list.*.unit_price' => ['required', 'numeric', 'min:0'],
            'item_list.*.unit_measure_id' => ['nullable', 'string', 'exists:unit_measures,id'],
            'item_list.*.batch' => ['nullable', 'string'],
            'item_list.*.color' => ['nullable', 'string'],
            'item_list.*.expire_date' => ['nullable', 'date'],
            'item_list.*.size_id' => ['nullable', 'string', 'exists:sizes,id'],
            'item_list.*.category_id' => ['nullable', 'string', 'exists:categories,id'],
            'item_list.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

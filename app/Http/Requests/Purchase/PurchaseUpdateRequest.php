<?php

namespace App\Http\Requests\Purchase;

use App\Enums\SalePurchaseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'number' => ['required', 'integer', 'min:1', Rule::unique('purchases', 'number')->ignore($this->purchase)->whereNull('deleted_at')->where('branch_id', $this->branch_id)],
            'supplier_id' => ['required', 'string', 'exists:ledgers,id'],
            'date' => ['required', 'date'],
            'warehouse_id' => ['required', 'string', 'exists:warehouses,id'],
            'discount' => ['nullable', 'numeric'],
            'discount_type' => ['nullable', 'string', 'in:percentage,currency'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'bank_account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'purchase_type' => ['nullable', 'string', Rule::in(SalePurchaseType::values())],
            'transaction_total' => ['required', 'numeric'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric'],
            'due_date' => ['nullable', 'date'],
            'item_list' => ['required', 'array'],
            'item_list.*.item_id' => ['required', 'string', 'exists:items,id'],
            'item_list.*.quantity' => ['required', 'numeric', 'min:0.0000001'],
            'item_list.*.unit_price' => ['required', 'numeric', 'min:0'],
            'item_list.*.free' => ['nullable', 'numeric', 'min:0'],
            'item_list.*.batch' => ['nullable', 'string'],
            'item_list.*.expire_date' => ['nullable', 'date'],
            'item_list.*.item_discount' => ['nullable', 'numeric', 'min:0'],
            'item_list.*.tax' => ['nullable', 'numeric', 'min:0'],
            'item_list.*.unit_measure_id' => ['required', 'string', 'exists:unit_measures,id'],
            'payment' => ['nullable', 'array'],
            'payment.method' => ['nullable', 'string'],
            'payment.account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'payment.amount' => ['nullable', 'numeric', 'min:0'],
            'payment.note' => ['nullable', 'string'],
        ];
    }
}

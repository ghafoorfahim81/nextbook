<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\TransactionStatus;
use App\Http\Requests\Concerns\BranchScopedUnique;
class PurchaseStoreRequest extends FormRequest
{
    use BranchScopedUnique;

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
            'number' => ['required', 'integer', 'min:1', $this->uniqueInCompany('purchases', column: 'number')],
            'supplier_id' => ['required', 'string', Rule::exists('ledgers', 'id')->where('type', 'supplier')],
            'purchase_order_id' => ['nullable', 'string', 'exists:purchase_orders,id'],
            'date' => ['required', 'date'],
            // Worked out on the server from the lines; whatever the form sends is ignored.
            'transaction_total' => ['nullable', 'numeric'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'bank_account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'purchase_type' => ['nullable', 'string', Rule::in(\App\Enums\SalePurchaseType::values())],
            'due_date' => ['nullable', 'date'],
            'payment' => ['nullable', 'array'],
            'payment.method' => ['nullable', 'string', Rule::in(\App\Enums\PaymentMethod::values())],
            'payment.amount' => ['nullable', 'numeric', 'min:0'],
            'payment.account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'payment.note' => ['nullable', 'string'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', 'in:percentage,currency'],
            'warehouse_id' => ['required', 'string', 'exists:warehouses,id'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'item_list' => ['required', 'array', 'min:1'],
            'item_list.*.item_id' => ['required', 'string', 'exists:items,id'],
            'item_list.*.variant_id' => ['nullable', 'string', 'exists:item_variants,id'],
            'item_list.*.batch' => ['nullable', 'string'],
            'item_list.*.expire_date' => ['nullable', 'date'],
            'item_list.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'item_list.*.unit_measure_id' => ['required', 'string', 'exists:unit_measures,id'],
            'item_list.*.unit_price' => ['required', 'numeric', 'min:0'],
            'item_list.*.item_discount' => ['nullable', 'numeric', 'min:0'],
            'item_list.*.free' => ['nullable', 'numeric', 'min:0'],
            'item_list.*.tax' => ['nullable', 'numeric', 'min:0'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}

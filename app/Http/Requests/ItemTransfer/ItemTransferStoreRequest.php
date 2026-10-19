<?php

namespace App\Http\Requests\ItemTransfer;

use App\Enums\TransactionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemTransferStoreRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'from_warehouse_id' => ['required', 'string', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'string', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'status' => ['nullable', 'string', Rule::in(TransactionStatus::values())],
            // The switch decides whether the freight fields are required: a
            // transfer without a cost must not be forced to name a bank account.
            'has_transfer_cost' => ['nullable', 'boolean'],
            'transfer_cost' => ['nullable', 'required_if:has_transfer_cost,true,1', 'numeric', 'min:0'],
            'bank_account_id' => ['nullable', 'required_if:has_transfer_cost,true,1', 'string', 'exists:accounts,id'],
            'expense_account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'currency_id' => ['nullable', 'required_if:has_transfer_cost,true,1', 'string', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'string', 'exists:items,id'],
            'items.*.variant_id' => ['nullable', 'string', 'exists:item_variants,id'],
            'items.*.batch' => ['nullable', 'string'],
            'items.*.expire_date' => ['nullable', 'date'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.measure_id' => ['required', 'string', 'exists:unit_measures,id'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}

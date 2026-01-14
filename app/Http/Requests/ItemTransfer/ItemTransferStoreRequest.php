<?php

namespace App\Http\Requests\ItemTransfer;

use App\Enums\TransferStatus;
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
            'from_store_id' => ['required', 'string', 'exists:stores,id'],
            'to_store_id' => ['required', 'string', 'exists:stores,id', 'different:from_store_id'],
            'status' => ['nullable', 'string', Rule::in(TransferStatus::values())],
            'transfer_cost' => ['nullable', 'numeric', 'min:0'],
            'branch_id' => ['required', 'string', 'exists:branches,id'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'string', 'exists:items,id'],
            'items.*.batch' => ['nullable', 'string'],
            'items.*.expire_date' => ['nullable', 'date'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.measure_id' => ['required', 'string', 'exists:unit_measures,id'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

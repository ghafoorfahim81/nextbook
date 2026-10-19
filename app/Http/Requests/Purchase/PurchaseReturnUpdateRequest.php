<?php

namespace App\Http\Requests\Purchase;

use App\Enums\PurchaseReturnReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseReturnUpdateRequest extends FormRequest
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
            'number' => ['required', 'integer', 'min:1', Rule::unique('purchase_returns', 'number')->ignore($this->purchase_return)->whereNull('deleted_at')->where('branch_id', $this->branch_id)],
            'purchase_id' => ['required', 'string', 'exists:purchases,id'],
            'date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', Rule::in(PurchaseReturnReason::values())],
            'description' => ['nullable', 'string'],
            'item_list' => ['required', 'array', 'min:1'],
            'item_list.*.purchase_item_id' => ['required', 'string', 'exists:purchase_items,id'],
            'item_list.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $ids = collect($this->input('item_list', []))->pluck('purchase_item_id');

            if ($ids->duplicates()->isNotEmpty()) {
                $validator->errors()->add('item_list', __('Each purchase item can only appear once per return.'));
            }
        });
    }
}

<?php

namespace App\Http\Requests\Inventory;

use App\Enums\LandedCostAllocationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LandedCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'purchase_id' => ['nullable', 'string', 'exists:purchases,id'],
            'purchase_ids' => ['nullable', 'array'],
            'purchase_ids.*' => ['string', 'distinct', 'exists:purchases,id'],
            'total_cost' => ['required', 'numeric', 'min:0.01'],
            'allocated_total' => ['nullable', 'numeric', 'min:0'],
            'allocation_method' => ['required', 'string', Rule::in(LandedCostAllocationMethod::values())],
            'status' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'string', 'exists:landed_cost_items,id'],
            'items.*.purchase_item_id' => ['nullable', 'string', 'exists:purchase_items,id'],
            'items.*.purchase_id' => ['nullable', 'string', 'exists:purchases,id'],
            'items.*.item_id' => ['nullable', 'string', 'exists:items,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.volume' => ['nullable', 'numeric', 'min:0'],
            'items.*.warehouse_id' => ['nullable', 'string', 'exists:warehouses,id'],
            'items.*.batch' => ['nullable', 'string'],
            'items.*.expire_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = collect($this->input('items', []))->filter(fn ($item) => !empty(data_get($item, 'item_id')));

            if ($items->isEmpty()) {
                $validator->errors()->add('items', __('general.landed_cost_no_items_to_allocate'));
            }

            if ($this->filled('allocated_total') && (float) $this->input('allocated_total') > (float) $this->input('total_cost', 0)) {
                $validator->errors()->add('allocated_total', __('general.landed_cost_allocated_total_cannot_exceed_total_cost'));
            }

            $items->each(function ($item, $index) use ($validator) {
                if (blank(data_get($item, 'quantity'))) {
                    $validator->errors()->add("items.$index.quantity", 'Quantity is required.');
                }

                if (blank(data_get($item, 'unit_cost'))) {
                    $validator->errors()->add("items.$index.unit_cost", 'Unit cost is required.');
                }
            });
        });
    }
}

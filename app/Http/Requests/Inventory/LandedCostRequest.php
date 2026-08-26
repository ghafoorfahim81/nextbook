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
            'bank_account_id' => ['required', 'string', 'exists:accounts,id'],
            'currency_id' => ['required', 'string', 'exists:currencies,id'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'total_cost' => ['required', 'numeric', 'min:0.01'],
            'allocated_total' => ['nullable', 'numeric', 'min:0'],
            'allocation_method' => ['required', 'string', Rule::in(LandedCostAllocationMethod::values())],
            'status' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'category_allocations' => ['nullable', 'array'],
            'category_allocations.*.landed_cost_category_id' => ['required_with:category_allocations', 'string', 'exists:landed_cost_categories,id'],
            'category_allocations.*.amount' => ['required_with:category_allocations', 'numeric', 'min:0.01'],
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
            'items.*.allocated_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.allocated_percentage' => ['nullable', 'numeric', 'min:0'],
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

            $totalCost = (float) $this->input('total_cost', 0);

            // The item-level allocation the client already computed (proportional
            // methods always sum to total_cost by construction; only 'manual' can
            // legitimately drift) must still equal total_cost — the same
            // requirement the UI blocks submission on.
            $itemsAllocatedTotal = $items->sum(fn ($item) => (float) data_get($item, 'allocated_amount', 0));

            if ($totalCost > 0 && abs(round($itemsAllocatedTotal, 2) - round($totalCost, 2)) > 0.01) {
                $validator->errors()->add('items', __('general.landed_cost_allocation_must_match_total_cost'));
            }

            // The category breakdown is where total_cost comes from on the
            // frontend, but nothing stops a direct API call from sending them out
            // of sync — so it is re-checked here too.
            $categoryAllocations = collect($this->input('category_allocations', []))
                ->filter(fn ($row) => filled(data_get($row, 'landed_cost_category_id')));

            if ($categoryAllocations->isNotEmpty()) {
                $categoryTotal = $categoryAllocations->sum(fn ($row) => (float) data_get($row, 'amount', 0));

                if ($totalCost > 0 && abs(round($categoryTotal, 2) - round($totalCost, 2)) > 0.01) {
                    $validator->errors()->add('category_allocations', __('general.landed_cost_allocation_must_match_total_cost'));
                }
            }
        });
    }
}

<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ItemType;
use App\Http\Requests\Inventory\Concerns\ValidatesItemPayload;
use App\Support\BusinessProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ItemStoreRequest extends FormRequest
{
    use ValidatesItemPayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            $this->itemRules(),
            $this->variantRules(),
            $this->openingRules(),
        );
    }

    /**
     * Fill in what the form does not send.
     *
     * Tracking defaults come from the company's trade, so a pharmacy gets
     * batch and expiry switched on without the operator remembering to tick
     * them, and a garment shop never sees the option at all.
     */
    protected function prepareForValidation(): void
    {
        $profile = BusinessProfile::current();
        $merge = [];

        if (blank($this->input('item_type'))) {
            $merge['item_type'] = ItemType::INVENTORY_MATERIALS->value;
        }

        foreach ($profile->defaults() as $flag => $value) {
            if ($this->input($flag) === null) {
                $merge[$flag] = $value;
            }
        }

        // Every item carries at least one variant. A trade with no choices
        // still gets one, empty, so variant_id is never null downstream.
        if (blank($this->input('variants'))) {
            $merge['variants'] = [[
                'attributes' => [],
                'sku' => $this->input('sku'),
                'barcode' => $this->input('barcode'),
                'sale_price' => $this->input('sale_price'),
                'purchase_price' => $this->input('purchase_price'),
                'minimum_stock' => $this->input('minimum_stock'),
                'maximum_stock' => $this->input('maximum_stock'),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
            ]];
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $this->applyPayloadChecks($validator);
    }
}

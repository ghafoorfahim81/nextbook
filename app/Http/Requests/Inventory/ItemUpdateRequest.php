<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Inventory\Concerns\ValidatesItemPayload;
use App\Models\Inventory\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ItemUpdateRequest extends FormRequest
{
    use ValidatesItemPayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $item = $this->route('item');
        $itemId = $item instanceof Item ? $item->getKey() : $item;

        return array_merge(
            // Passing the id makes every uniqueness check ignore this row.
            // Previously `sku` reused the store request's rule with no ignore,
            // so an item conflicted with itself on save.
            $this->itemRules($itemId),
            $this->variantRules(),
            $this->openingRules(),
        );
    }

    /**
     * Variants absent from the payload are removed, so a form that sends none
     * still has to describe the single default one.
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('variants'))) {
            $this->merge([
                'variants' => [[
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
                ]],
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $this->applyPayloadChecks($validator);
    }
}

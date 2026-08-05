<?php

namespace App\Http\Requests\Inventory\Concerns;

use App\Enums\CostingMethod;
use App\Enums\ItemType;
use App\Enums\PricingMethod;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Rules shared by the item store and update requests.
 *
 * They were previously duplicated and had drifted apart — the update request
 * still carried the store request's `sku` rule, which meant an item conflicted
 * with itself on save.
 *
 * @mixin \Illuminate\Foundation\Http\FormRequest
 */
trait ValidatesItemPayload
{
    /**
     * The branch every uniqueness check is scoped to.
     */
    protected function branchId(): ?string
    {
        $branchId = $this->user()?->branch_id;

        if ($branchId !== null) {
            return $branchId;
        }

        return app()->bound('active_branch_id') ? app('active_branch_id') : null;
    }

    /**
     * @param  string|null  $ignoreId  Item being updated, if any.
     */
    protected function itemRules(?string $ignoreId = null): array
    {
        $branchId = $this->branchId();

        // Scope to the live rows of this branch. The previous rule read
        // `unique:items,name,NULL,id,branch_id,NULL,...`, which tests
        // `branch_id IS NULL` and therefore never matched anything.
        $unique = fn (string $column) => Rule::unique('items', $column)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at'))
            ->when($ignoreId !== null, fn ($rule) => $rule->ignore($ignoreId));

        return [
            'name' => ['required', 'string', 'max:256', $unique('name')],
            'code' => ['required', 'string', 'max:256', $unique('code')],
            'sku' => ['nullable', 'string', 'max:100', $unique('sku')],
            'barcode' => ['nullable', 'string', 'max:100', $unique('barcode')],

            'item_type' => ['nullable', 'string', Rule::in(ItemType::values())],
            'generic_name' => ['nullable', 'string', 'max:256'],
            'packing' => ['nullable', 'string', 'max:256'],
            'description' => ['nullable', 'string', 'max:2000'],

            'unit_measure_id' => ['required', 'string', 'exists:unit_measures,id'],
            'brand_id' => ['nullable', 'string', 'exists:brands,id'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'size_id' => ['nullable', 'string', 'exists:sizes,id'],
            'default_warehouse_id' => ['nullable', 'string', 'exists:warehouses,id'],

            'asset_account_id' => ['required', 'string', 'exists:accounts,id'],
            'income_account_id' => ['required', 'string', 'exists:accounts,id'],
            'cost_account_id' => ['required', 'string', 'exists:accounts,id'],

            // --- Tracking -------------------------------------------------
            'is_batch_tracked' => ['nullable', 'boolean'],
            'is_expiry_tracked' => ['nullable', 'boolean'],
            'is_serial_tracked' => ['nullable', 'boolean'],
            'is_color_tracked' => ['nullable', 'boolean'],
            'is_size_tracked' => ['nullable', 'boolean'],

            // --- Behaviour ------------------------------------------------
            'is_active' => ['nullable', 'boolean'],
            'is_stockable' => ['nullable', 'boolean'],
            'is_sellable' => ['nullable', 'boolean'],
            'is_purchasable' => ['nullable', 'boolean'],
            'is_weighted' => ['nullable', 'boolean'],
            'has_variants' => ['nullable', 'boolean'],
            'allow_negative_stock' => ['nullable', 'boolean'],
            'requires_prescription' => ['nullable', 'boolean'],
            'is_controlled' => ['nullable', 'boolean'],

            'costing_method' => ['nullable', Rule::in(CostingMethod::values())],
            'pricing_method' => ['nullable', Rule::in(PricingMethod::values())],

            // --- Pricing --------------------------------------------------
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'margin_percentage' => ['nullable', 'numeric'],
            'rate_a' => ['nullable', 'numeric', 'min:0'],
            'rate_b' => ['nullable', 'numeric', 'min:0'],
            'rate_c' => ['nullable', 'numeric', 'min:0'],

            // --- Stock control --------------------------------------------
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'maximum_stock' => ['nullable', 'numeric', 'min:0', 'gte:minimum_stock'],
            'reorder_quantity' => ['nullable', 'numeric', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'rack_no' => ['nullable', 'string', 'max:100'],
            'fast_search' => ['nullable', 'string', 'max:100'],

            // --- Physical --------------------------------------------------
            'weight' => ['nullable', 'numeric', 'min:0'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],

            // --- Sourcing & compliance --------------------------------------
            'manufacturer' => ['nullable', 'string', 'max:256'],
            'model' => ['nullable', 'string', 'max:256'],
            'country_of_origin' => ['nullable', 'string', 'max:256'],
            'hs_code' => ['nullable', 'string', 'max:50'],
            'warranty_months' => ['nullable', 'integer', 'min:0'],
            'shelf_life_days' => ['nullable', 'integer', 'min:0'],
            'min_shelf_life_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'storage_zone' => ['nullable', 'string', 'max:50'],

            'colors' => ['nullable', 'array'],
            'attributes' => ['nullable', 'array'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],

            'attachments' => ['nullable', 'array'],
            'attachments.*' => [
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp',
                'max:10240',
            ],
        ];
    }

    /**
     * Every item carries at least one variant, so `variant_id` can be relied
     * on downstream without a null check.
     */
    protected function variantRules(): array
    {
        return [
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'string', 'exists:item_variants,id'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.name' => ['nullable', 'string', 'max:256'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'variants.*.maximum_stock' => ['nullable', 'numeric', 'min:0'],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function openingRules(): array
    {
        return [
            'openings' => ['nullable', 'array'],
            'openings.*.id' => ['nullable', 'string'],
            'openings.*.variant_index' => ['nullable', 'integer', 'min:0'],
            'openings.*.batch' => ['nullable', 'string', 'max:100'],
            'openings.*.expire_date' => ['nullable', 'date'],
            'openings.*.quantity' => ['nullable', 'numeric', 'min:0'],
            // The old rule was `required_with:openings.*.quantity>0`, which is
            // not a valid rule expression and never fired.
            'openings.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'openings.*.warehouse_id' => ['nullable', 'string', 'exists:warehouses,id'],
            'openings.*.color' => ['nullable', 'string'],
            'openings.*.size_id' => ['nullable', 'string', 'exists:sizes,id'],
            'openings.*.status' => ['nullable', 'string'],
        ];
    }

    /**
     * Cross-field checks the rule array cannot express.
     */
    protected function applyPayloadChecks(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $variants = (array) $this->input('variants', []);

            $this->assertSingleDefaultVariant($validator, $variants);
            $this->assertUniqueWithinPayload($validator, $variants, 'sku');
            $this->assertUniqueWithinPayload($validator, $variants, 'barcode');
            $this->assertUniqueVariantAttributes($validator, $variants);
            $this->assertOpeningPricesPresent($validator);
        });
    }

    private function assertSingleDefaultVariant(Validator $validator, array $variants): void
    {
        $defaults = collect($variants)->filter(fn ($v) => (bool) ($v['is_default'] ?? false));

        if ($defaults->count() > 1) {
            $validator->errors()->add('variants', __('item.validation.single_default_variant'));
        }
    }

    private function assertUniqueWithinPayload(Validator $validator, array $variants, string $field): void
    {
        $seen = [];

        foreach ($variants as $index => $variant) {
            $value = $variant[$field] ?? null;

            if (blank($value)) {
                continue;
            }

            if (isset($seen[$value])) {
                $validator->errors()->add(
                    "variants.{$index}.{$field}",
                    __('item.validation.duplicate_variant_field', ['field' => $field])
                );
            }

            $seen[$value] = true;
        }
    }

    /**
     * Two variants meaning the same thing — "16GB / Black" twice — would
     * otherwise split the stock between them.
     */
    private function assertUniqueVariantAttributes(Validator $validator, array $variants): void
    {
        $seen = [];

        foreach ($variants as $index => $variant) {
            $key = \App\Models\Inventory\ItemVariant::makeKey(
                (array) ($variant['attributes'] ?? [])
            );

            if (isset($seen[$key])) {
                $validator->errors()->add(
                    "variants.{$index}.attributes",
                    __('item.validation.duplicate_variant')
                );
            }

            $seen[$key] = true;
        }
    }

    private function assertOpeningPricesPresent(Validator $validator): void
    {
        foreach ((array) $this->input('openings', []) as $index => $opening) {
            $quantity = (float) ($opening['quantity'] ?? 0);

            if ($quantity > 0 && blank($opening['unit_price'] ?? null)) {
                $validator->errors()->add(
                    "openings.{$index}.unit_price",
                    __('item.validation.opening_price_required')
                );
            }

            if ($quantity > 0 && blank($opening['warehouse_id'] ?? null)) {
                $validator->errors()->add(
                    "openings.{$index}.warehouse_id",
                    __('item.validation.opening_warehouse_required')
                );
            }
        }
    }
}

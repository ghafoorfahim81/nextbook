<?php
// app/Http/Requests/Inventory/FastEntryRequest.php
namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FastEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // or Gate/Policy
    }

    public function rules(): array
    {
        $branchId = $this->user()?->branch_id;

        return [
            'items'                       => ['required','array','min:1'],
            // `distinct` catches two rows in the same submission naming the same
            // item — the unique rule only looks at what is already stored.
            'items.*.name'                => ['required','string','max:255','distinct:ignore_case', Rule::unique('items')->where(fn ($q) => $q
                        ->where('branch_id', $branchId)
                        ->whereNull('deleted_at')
                    )],
            'items.*.code'                => ['nullable','string','max:50','distinct', Rule::unique('items')->where(fn ($q) => $q
                        ->where('branch_id', $branchId)
                        ->whereNull('deleted_at')
                    )],
            'items.*.barcode'             => ['nullable','string','max:100','distinct'],
            'items.*.category_id'         => ['nullable','exists:categories,id'],
            'items.*.measure_id'          => ['required','exists:unit_measures,id'],
            'items.*.brand_id'            => ['nullable','exists:brands,id'],
            'items.*.purchase_price'      => ['nullable','numeric','min:0'],
            'items.*.sale_price'          => ['nullable','numeric','min:0'],
            // Final landed cost per unit. Blank means "cost it at the purchase price".
            'items.*.cost'                => ['nullable','numeric','min:0'],
            'items.*.batch'               => ['nullable','string','max:100'],
            'items.*.expire_date'         => ['nullable','date'],
            'items.*.quantity'            => ['nullable','numeric','min:0'],
            'items.*.warehouse_id'        => ['nullable','exists:warehouses,id'],
        ];
    }

    /**
     * Without this every message reads "The items.3.name field is required."
     * The row number is already shown by the grid, which highlights the cell.
     */
    public function attributes(): array
    {
        $labels = [
            'name'           => __('general.name'),
            'code'           => __('general.code'),
            'barcode'        => __('general.barcode'),
            'category_id'    => __('general.category'),
            'measure_id'     => __('general.unit_measure'),
            'brand_id'       => __('general.brand'),
            'purchase_price' => __('general.purchase_price'),
            'sale_price'     => __('general.sale_price'),
            'cost'           => __('general.final_cost'),
            'batch'          => __('general.batch'),
            'expire_date'    => __('general.expire_date'),
            'quantity'       => __('general.opening_amount'),
            'warehouse_id'   => __('general.warehouse'),
        ];

        $attributes = [];

        foreach (array_keys((array) $this->input('items', [])) as $index) {
            foreach ($labels as $field => $label) {
                $attributes["items.{$index}.{$field}"] = $label;
            }
        }

        return $attributes;
    }

    /**
     * Cross-field rules that need the row index, which `items.*` rules cannot see.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('items', []) as $index => $row) {
                $quantity = (float) ($row['quantity'] ?? 0);

                // An opening quantity has to land somewhere — StockService keys the
                // balance row on the warehouse, so it cannot be null.
                if ($quantity > 0 && empty($row['warehouse_id'])) {
                    $validator->errors()->add(
                        "items.{$index}.warehouse_id",
                        __('validation.required', ['attribute' => __('general.warehouse')])
                    );
                }

                // A batch or expiry with no quantity would be silently dropped —
                // the opening is only posted when there is stock to post.
                if ($quantity <= 0 && (filled($row['batch'] ?? null) || filled($row['expire_date'] ?? null))) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        __('validation.required', ['attribute' => __('general.opening_amount')])
                    );
                }
            }
        });
    }
}

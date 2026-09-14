<?php
// app/Http/Requests/Inventory/FastOpeningRequest.php
namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class FastOpeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // or Gate/Policy
    }

    public function rules(): array
    {
        return [
            'items'                   => ['required','array','min:1'],
            'items.*.item_id'         => ['required','string','exists:items,id'],
            // A blank quantity is how the operator says "not this one" — the row
            // is skipped rather than rejected, so only what is typed is checked.
            'items.*.quantity'        => ['nullable','numeric','min:0'],
            'items.*.batch'           => ['nullable','string','max:100'],
            'items.*.expire_date'     => ['nullable','date'],
            'items.*.cost'            => ['nullable','numeric','min:0'],
            'items.*.unit_measure_id' => ['required','exists:unit_measures,id'],
            'items.*.warehouse_id'    => ['nullable','exists:warehouses,id'],
        ];
    }

    /**
     * Without this every message reads "The items.3.quantity field is required."
     */
    public function attributes(): array
    {
        $labels = [
            'quantity'        => __('general.opening_amount'),
            'batch'           => __('general.batch'),
            'expire_date'     => __('general.expire_date'),
            'cost'            => __('general.final_cost'),
            'unit_measure_id' => __('general.unit_measure'),
            'warehouse_id'    => __('general.warehouse'),
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

                // Rows with no quantity are skipped entirely, so nothing else on
                // them has to be filled in.
                if ($quantity <= 0) {
                    continue;
                }

                // StockService keys the balance row on the warehouse: an opening
                // with nowhere to sit cannot be posted.
                if (empty($row['warehouse_id'])) {
                    $validator->errors()->add(
                        "items.{$index}.warehouse_id",
                        __('validation.required', ['attribute' => __('general.warehouse')])
                    );
                }

                // The opening layer is costed at this, and it is what the GL
                // entry is worth. A zero would silently book stock at nothing.
                if (($row['cost'] ?? null) === null || $row['cost'] === '' || (float) $row['cost'] <= 0) {
                    $validator->errors()->add(
                        "items.{$index}.cost",
                        __('validation.required', ['attribute' => __('general.final_cost')])
                    );
                }
            }
        });
    }
}

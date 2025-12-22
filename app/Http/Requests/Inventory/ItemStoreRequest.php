<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ItemStoreRequest extends FormRequest
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
            'name' => 'required|min:3|unique:items,name,NULL,id,deleted_at,NULL',
            'code' => 'required|string|unique:items,code,NULL,id,deleted_at,NULL',
            'generic_name' => ['nullable', 'string'],
            'packing' => ['nullable', 'string'],
            'barcode' => ['nullable', 'string'],
            'unit_measure_id' => ['required', 'string', 'exists:unit_measures,id'],
            'brand_id' => ['nullable', 'string', 'exists:brands,id'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'minimum_stock' => ['nullable', 'numeric'],
            'maximum_stock' => ['nullable', 'numeric'],
            'colors' => ['nullable', 'string'],
            'size' => ['nullable', 'string'],
            'photo' => ['nullable', 'string'],
            'purchase_price' => ['nullable', 'numeric'],
            'cost' => ['nullable', 'numeric'],
            'sale_price' => ['required', 'numeric'],
            'rate_a' => ['nullable', 'numeric'],
            'rate_b' => ['nullable', 'numeric'],
            'rate_c' => ['nullable', 'numeric'],
            'rack_no' => ['nullable', 'string'],
            'fast_search' => ['nullable', 'string'],
            'openings' => ['nullable', 'array'],
            'openings.*.batch' => ['nullable', 'string'],
            'openings.*.expire_date' => ['nullable', 'date'],
            'openings.*.quantity' => ['nullable', 'numeric'],
            'openings.*.store_id' => ['nullable', 'string', 'exists:stores,id'],
        ];
    }
}

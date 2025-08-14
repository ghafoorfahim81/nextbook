<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ItemUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:256', \Illuminate\Validation\Rule::unique('items')->ignore($this->route('item'))],
            'code' => ['required', 'string', 'max:256', \Illuminate\Validation\Rule::unique('items')->ignore($this->route('item'))],
            'generic_name' => ['nullable', 'string'],
            'packing' => ['nullable', 'string'],
            'barcode' => ['nullable', 'string'],
            'unit_measure_id' => ['required', 'integer', 'exists:unit_measures,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'minimum_stock' => ['nullable', 'numeric'],
            'maximum_stock' => ['nullable', 'numeric'],
            'colors' => ['nullable', 'string'],
            'size' => ['nullable', 'string'],
            'photo' => ['nullable', 'string'],
            'purchase_price' => ['nullable', 'numeric'],
            'cost' => ['nullable', 'numeric'],
            'mrp_rate' => ['required', 'numeric'],
            'rate_a' => ['nullable', 'numeric'],
            'rate_b' => ['nullable', 'numeric'],
            'rate_c' => ['nullable', 'numeric'],
            'rack_no' => ['nullable', 'integer'],
            'fast_search' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ];
    }
}

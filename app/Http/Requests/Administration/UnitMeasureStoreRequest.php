<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

class UnitMeasureStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:unit_measures,name'],
            'unit' => ['required', 'string'],
            'symbol' => ['required', 'string'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'quantity_id' => ['required', 'integer', 'exists:quantities,id'],
            'value' => ['nullable', 'numeric'],
            'created_by' => ['required'],
            'updated_by' => ['nullable'],
        ];
    }
}

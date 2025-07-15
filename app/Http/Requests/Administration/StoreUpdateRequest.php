<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

class StoreUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:stores,name'],
            'address' => ['nullable', 'string'],
            'is_main' => ['required'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'created_by' => ['required'],
            'updated_by' => ['nullable'],
        ];
    }
}

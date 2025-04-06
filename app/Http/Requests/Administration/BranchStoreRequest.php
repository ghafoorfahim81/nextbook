<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

class BranchStoreRequest extends FormRequest
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
            'name' => ['required', 'string'],
            'is_main' => ['required'],
            'parent_id' => ['nullable', 'integer', 'exists:branches,id'],
            'sub_domain' => ['nullable', 'string'],
            'remark' => ['nullable', 'string'], 
            'updated_by' => ['nullable'],
        ];
    }
}

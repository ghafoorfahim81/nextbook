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
            'name' => ['required', 'string', 'unique:branches,name'],
            'is_main' => ['nullable', 'boolean'],
            'parent_id' => ['nullable', 'string', 'exists:branches,id'],
            'location' => ['nullable', 'string'],
            'sub_domain' => ['nullable', 'string'],
            'remark' => ['nullable', 'string'],
        ];
    }
}

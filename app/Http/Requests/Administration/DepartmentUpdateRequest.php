<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

class DepartmentUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:departments,name'],
            'remark' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:departments,id'],
            'created_by' => ['required'],
            'updated_by' => ['nullable'],
        ];
    }
}

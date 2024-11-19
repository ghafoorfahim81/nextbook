<?php

namespace App\Http\Requests\ControlPanel;

use Illuminate\Foundation\Http\FormRequest;

class DesignationStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:designations,name'],
            'remark' => ['nullable', 'string'],
            'created_by' => ['required'],
            'updated_by' => ['nullable'],
            'deleted_by' => ['nullable'],
        ];
    }
}

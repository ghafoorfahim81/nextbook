<?php

namespace App\Http\Requests\ControlPanel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DesignationUpdateRequest extends FormRequest
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
            'name' => ['required', 'string',
                Rule::unique('designations')->ignore($this->route('designation')),
            ],
            'remark' => ['nullable', 'string'],
            'created_by' => ['nullable'],
            'updated_by' => ['nullable'],
            'deleted_by' => ['nullable'],
        ];
    }
}

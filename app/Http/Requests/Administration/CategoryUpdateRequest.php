<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

class CategoryUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:256', \Illuminate\Validation\Rule::unique('categories')->ignore($this->route('category'))->whereNull('deleted_at')],
            'parent_id' => ['nullable', 'string', 'exists:categories,id'],
            'remark' => ['nullable', 'string'],
        ];
    }
}

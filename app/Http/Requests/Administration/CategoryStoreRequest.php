<?php

namespace App\Http\Requests\Administration;

use App\Http\Requests\Concerns\BranchScopedUnique;
use Illuminate\Foundation\Http\FormRequest;

class CategoryStoreRequest extends FormRequest
{
    use BranchScopedUnique;

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
            'name' => ['required', 'string', $this->uniqueInBranch('categories')],
            'parent_id' => ['nullable', 'string', 'exists:categories,id'],
            'remark' => ['nullable', 'string'],
        ];
    }
}

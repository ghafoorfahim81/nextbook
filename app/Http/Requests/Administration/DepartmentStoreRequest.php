<?php

namespace App\Http\Requests\Administration;

use App\Http\Requests\Concerns\BranchScopedUnique;
use Illuminate\Foundation\Http\FormRequest;

class DepartmentStoreRequest extends FormRequest
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
            'name' => ['required', 'string', $this->uniqueInBranch('departments')],
            'remark' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:departments,id'],
            'created_by' => ['required'],
            'updated_by' => ['nullable'],
        ];
    }
}

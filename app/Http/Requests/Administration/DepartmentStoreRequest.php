<?php

namespace App\Http\Requests\Administration;

use App\Http\Requests\Concerns\BranchScopedUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'code' => ['required', 'string', $this->uniqueInBranch('departments', column: 'code')],
            'remark' => ['nullable', 'string'],
            'parent_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where(
                    'branch_id',
                    $this->activeBranchId()
                )),
            ],
        ];
    }
}

<?php

namespace App\Http\Requests\Administration;

use App\Http\Requests\Concerns\BranchScopedUnique;
use App\Models\Administration\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DepartmentUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', $this->uniqueInBranch('departments', $this->route('department'))],
            'code' => ['required', 'string', $this->uniqueInBranch('departments', $this->route('department'), 'code')],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $department = $this->route('department');
            $parentId = $this->input('parent_id');

            if (! $parentId || ! $department) {
                return;
            }

            if ($parentId === $department->id) {
                $validator->errors()->add('parent_id', __('validation.not_in', ['attribute' => __('general.parent')]));
                return;
            }

            $parent = Department::find($parentId);
            while ($parent) {
                if ($parent->parent_id === $department->id) {
                    $validator->errors()->add('parent_id', __('validation.not_in', ['attribute' => __('general.parent')]));
                    break;
                }

                $parent = $parent->parent;
            }
        });
    }
}

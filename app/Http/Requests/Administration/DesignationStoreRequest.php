<?php

namespace App\Http\Requests\Administration;

use App\Http\Requests\Concerns\BranchScopedUnique;
use App\Models\Administration\Designation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Blueprint scaffold this replaces validated a single `designation` field
 * that the form never sent and the table does not have, so every submission
 * failed while every real column went unvalidated.
 */
class DesignationStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->designationId();

        return [
            'name' => ['required', 'string', 'max:150', $this->uniqueInBranch('designations', $id)],
            'code' => ['nullable', 'string', 'max:50', $this->uniqueInBranch('designations', $id)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'grade_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'remark' => ['nullable', 'string'],
        ];
    }

    protected function designationId(): ?string
    {
        $designation = $this->route('designation');

        if ($designation instanceof Designation) {
            return (string) $designation->id;
        }

        return $designation ? (string) $designation : null;
    }
}

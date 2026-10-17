<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     *
     * Both `name` and `parent_id` are scoped to the acting company. Two
     * companies may each have a "Kabul" branch, and a sub-branch may only be
     * parented inside its own company — an unscoped `exists:branches,id` would
     * accept any branch id in the database, which is the same hole
     * `SwitchBranchController` has.
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => [
                'required',
                'string',
                Rule::unique('branches', 'name')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at'),
            ],
            'is_main' => ['nullable', 'boolean'],
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'location' => ['nullable', 'string'],
            'remark' => ['nullable', 'string'],
        ];
    }
}

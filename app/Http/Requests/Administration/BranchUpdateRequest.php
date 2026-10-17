<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchUpdateRequest extends FormRequest
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
     * Scoped to the acting company for the reasons documented on
     * BranchStoreRequest. `parent_id` additionally rejects the branch itself,
     * which the composite foreign key cannot catch — a row may legally
     * reference itself as far as the constraint is concerned.
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $branchId = $this->route('branch')?->id ?? $this->route('branch');

        return [
            'name' => [
                'required',
                'string',
                Rule::unique('branches', 'name')
                    ->ignore($branchId)
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at'),
            ],
            'is_main' => ['nullable', 'boolean'],
            'parent_id' => [
                'nullable',
                'string',
                Rule::notIn([$branchId]),
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'location' => ['nullable', 'string'],
            'remark' => ['nullable', 'string'],
        ];
    }
}

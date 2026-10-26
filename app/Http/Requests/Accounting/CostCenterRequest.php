<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Concerns\BranchScopedUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Creating and editing a cost center (phase 7). */
class CostCenterRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $costCenter = $this->route('cost_center');

        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueInCompany('cost_centers', $costCenter)],
            'code' => ['nullable', 'string', 'max:30', $this->uniqueInCompany('cost_centers', $costCenter, 'code')],
            // A parent from the same books (the policies hide every other
            // company's), and never the cost center itself.
            'parent_id' => [
                'nullable', 'string', 'exists:cost_centers,id',
                Rule::notIn(array_filter([$costCenter?->id ?? $costCenter])),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}

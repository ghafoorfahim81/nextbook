<?php

namespace App\Http\Requests\Administration;

use App\Http\Requests\Concerns\BranchScopedUnique;
use App\Models\Administration\LandedCostCategory;
use Illuminate\Foundation\Http\FormRequest;

class LandedCostCategoryStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->landedCostCategoryId();

        return [
            'name' => ['required', 'string', 'max:150', $this->uniqueInBranch('landed_cost_categories', $id)],
            'remark' => ['nullable', 'string'],
        ];
    }

    protected function landedCostCategoryId(): ?string
    {
        $landedCostCategory = $this->route('landed_cost_category');

        if ($landedCostCategory instanceof LandedCostCategory) {
            return (string) $landedCostCategory->id;
        }

        return $landedCostCategory ? (string) $landedCostCategory : null;
    }
}

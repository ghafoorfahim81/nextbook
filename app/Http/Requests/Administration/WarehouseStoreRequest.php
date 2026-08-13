<?php

namespace App\Http\Requests\Administration;

use App\Http\Requests\Concerns\BranchScopedUnique;
use Illuminate\Foundation\Http\FormRequest;

class WarehouseStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', $this->uniqueInBranch('warehouses')],
            'address' => ['nullable', 'string'],
            'is_main' => ['nullable', 'boolean'],
        ];
    }
}


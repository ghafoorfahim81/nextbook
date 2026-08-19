<?php

namespace App\Http\Requests\Administration;

use App\Http\Requests\Concerns\BranchScopedUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SizeUpdateRequest extends FormRequest
{
    use BranchScopedUnique;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', $this->uniqueInBranch('sizes', $this->route('size'))],
            'code' => ['required', 'string', $this->uniqueInBranch('sizes', $this->route('size'))],
        ];
    }
}

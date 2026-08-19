<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\Concerns\BranchScopedUnique;
use Illuminate\Foundation\Http\FormRequest;

class AccountTypeStoreRequest extends FormRequest
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
            'name' => ['required', 'string', $this->uniqueInBranch('account_types')],
            'remark' => ['nullable', 'string'],
            'slug' => ['nullable', 'string'],
            'is_main' => ['nullable', 'boolean'],
        ];
    }
}

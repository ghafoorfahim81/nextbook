<?php

namespace App\Http\Requests\UserManagement;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at'), 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['nullable', 'in:' . implode(',', array_column(UserStatus::cases(), 'value'))],
            'branch_id' => ['nullable', 'string', 'exists:branches,id'],
            'company_id' => ['nullable', 'string', 'exists:companies,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ];
    }   
}


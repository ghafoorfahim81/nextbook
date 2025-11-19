<?php

namespace App\Http\Requests\UserManagement;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user');
        
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $userId],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'branch_id' => ['nullable', 'string', 'exists:branches,id'],
            'company_id' => ['nullable', 'string', 'exists:companies,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
        ];
    }
}


<?php

namespace App\Http\Requests\UserManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $this->user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed' ],
        
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,id'],
        
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,id'],
        ];
        
    }
}


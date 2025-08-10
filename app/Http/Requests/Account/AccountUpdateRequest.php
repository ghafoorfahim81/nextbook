<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class AccountUpdateRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'unique:accounts,name'],
            'number' => ['required', 'string', 'unique:accounts,number'],
            'account_type_id' => ['required', 'string', 'exists:account_types,id'],
            'remark' => ['nullable', 'string'],
            'created_by' => ['required'],
            'updated_by' => ['nullable'],
        ];
    }
}

<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class OwnerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255' , 'unique:owners,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'father_name' => ['required', 'string', 'max:255'],
            'nic' => ['nullable', 'string', 'max:255' , 'unique:owners,nic,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'email' => ['nullable', 'email', 'max:255' , 'unique:owners,email,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'address' => ['nullable', 'string'],
            'phone_number' => ['nullable', 'string', 'max:255' , 'unique:owners,phone_number,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'share_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'profit_share_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'capital_account_id' => ['required', 'string', 'exists:accounts,id'],
            'drawing_account_id' => ['required', 'string', 'exists:accounts,id'],
            // Special create fields
            'amount' => ['nullable', 'numeric', 'min:0'],
            'bank_account_id' => ['nullable', 'string', 'exists:accounts,id','required_with: amount'],
            'opening_currency_id' => ['nullable', 'string', 'exists:currencies,id','required_with: amount'],
            'rate' => ['nullable', 'numeric', 'min:0','required_with: amount'],
        ];
    }
}


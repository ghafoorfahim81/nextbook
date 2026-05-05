<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class OwnerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255' , \Illuminate\Validation\Rule::unique('owners')->ignore($this->route('owner'))->whereNull('deleted_at')->where('branch_id', $this->user()->current_branch_id)    ],
            'father_name' => ['required', 'string', 'max:255'],
            'nic' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255' , \Illuminate\Validation\Rule::unique('owners')->ignore($this->route('owner'))->whereNull('deleted_at')->where('branch_id', $this->user()->current_branch_id)],
            'address' => ['nullable', 'string'],
            'phone_number' => ['nullable', 'string', 'max:255' , \Illuminate\Validation\Rule::unique('owners')->ignore($this->route('owner'))->whereNull('deleted_at')->where('branch_id', $this->user()->current_branch_id)],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'share_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'profit_share_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'capital_account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'drawing_account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'bank_account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

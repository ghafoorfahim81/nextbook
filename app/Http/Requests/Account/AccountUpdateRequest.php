<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\TransactionType;
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
        // The route-model-bound account instance (parameter name: chart_of_account)
        $chartOfAccount = $this->route('chart_of_account');

        return [
            'name' => ['required', 'string', 'max:256', Rule::unique('accounts')->ignore($chartOfAccount->id)->whereNull('deleted_at')->where('branch_id', $this->branch_id)],
            'local_name' => ['nullable', 'string', 'max:256', Rule::unique('accounts')->ignore($chartOfAccount->id)->whereNull('deleted_at')->where('branch_id', $this->branch_id)],
            'number' => ['required', 'string', 'max:256', Rule::unique('accounts')->ignore($chartOfAccount->id)->whereNull('deleted_at')->where('branch_id', $this->branch_id)],
            'account_type_id' => ['required', 'string', 'exists:account_types,id'],
            'slug' => ['nullable', 'string', 'max:256', Rule::unique('accounts')->ignore($chartOfAccount->id)->whereNull('deleted_at')->where('branch_id', $this->branch_id)],
            'remark' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_main' => ['nullable', 'boolean'],
            'parent_id' => ['nullable', 'string', 'exists:accounts,id'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric','required_with:currency_id'],
            'amount' => ['nullable', 'numeric','required_with:transaction_type'],
            'transaction_type' => ['nullable', 'string', Rule::in(TransactionType::values())],
        ];
    }
}

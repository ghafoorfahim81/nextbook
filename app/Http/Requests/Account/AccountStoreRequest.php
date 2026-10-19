<?php

namespace App\Http\Requests\Account;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Http\Requests\Concerns\BranchScopedUnique;

class AccountStoreRequest extends FormRequest
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
            'name' => ['required', 'string', \Illuminate\Validation\Rule::unique('accounts')->ignore($this->route('account'))->whereNull('deleted_at')],
            'local_name' => ['nullable', 'string', \Illuminate\Validation\Rule::unique('accounts')->ignore($this->route('account'))->whereNull('deleted_at')],
            // Per company: another company's chart of accounts may use the same
            // number, and must not block this one.
            'number' => ['required', 'string', $this->uniqueInCompany('accounts', $this->route('account'), 'number')],
            'account_type_id' => ['required', 'string', 'exists:account_types,id'],
            'parent_id' => ['nullable', 'string', 'exists:accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],
            'slug' => ['nullable', 'string'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric','required_with:currency_id'],
            'amount' => ['nullable', 'numeric', 'min:0', 'required_with:currency_id'],
            'transaction_type' => ['nullable', 'string', Rule::in(TransactionType::values())],
        ];
    }
}

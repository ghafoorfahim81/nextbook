<?php

namespace App\Http\Requests\AccountTransfer;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Concerns\BranchScopedUnique;

class AccountTransferStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['nullable', 'max:255', $this->uniqueInCompany('account_transfers', column: 'number')],
            'date' => ['required', 'date'],
            'from_account_id' => ['required', 'exists:accounts,id'],
            'to_account_id' => ['required', 'different:from_account_id', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'rate' => ['required', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}



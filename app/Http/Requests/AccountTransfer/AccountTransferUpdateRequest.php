<?php

namespace App\Http\Requests\AccountTransfer;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Concerns\BranchScopedUnique;

class AccountTransferUpdateRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['nullable', 'max:255', $this->uniqueInCompany('account_transfers', $this->route('account_transfer'), 'number')],
            'date' => ['nullable', 'date'],
            'from_account_id' => ['nullable', 'exists:accounts,id'],
            'to_account_id' => ['nullable', 'different:from_account_id', 'exists:accounts,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}



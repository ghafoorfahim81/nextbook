<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Concerns\BranchScopedUnique;

class DrawingUpdateRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['nullable', 'string', 'max:50', $this->uniqueInCompany('drawings', $this->route('drawing'), 'number')],
            'owner_id' => ['required', 'string', 'exists:owners,id'],
            'bank_account_id' => ['required', 'string', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_id' => ['required', 'string', 'exists:currencies,id'],
            'rate' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'narration' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}

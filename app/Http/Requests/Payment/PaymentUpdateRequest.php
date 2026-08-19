<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMode;
use App\Http\Requests\Concerns\SettlesOpenItems;
use App\Services\Accounting\SettlementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentUpdateRequest extends FormRequest
{
    use SettlesOpenItems;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'number' => ['sometimes', 'required', 'integer', 'max:255'],
            'date' => ['sometimes', 'required', 'date'],
            'ledger_id' => ['sometimes', 'required', 'exists:ledgers,id'],
            'payment_mode' => ['sometimes', 'nullable', Rule::in(PaymentMode::values())],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'bank_account_id' => ['sometimes', 'required', 'exists:accounts,id'],
            'currency_id' => ['sometimes', 'required', 'exists:currencies,id'],
            'rate' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'cheque_no' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:10240'],
        ], $this->settlementRules(sometimes: true));
    }

    /** A payment sends money OUT, whoever the party is. */
    protected function settlementDirection(): string
    {
        return SettlementService::DIRECTION_OUT;
    }

    public function withValidator($validator): void
    {
        $this->validateSettlementSelection($validator);
    }
}

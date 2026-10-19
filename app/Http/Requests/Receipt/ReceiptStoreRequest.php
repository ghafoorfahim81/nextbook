<?php

namespace App\Http\Requests\Receipt;

use App\Enums\PaymentMode;
use App\Http\Requests\Concerns\SettlesOpenItems;
use App\Services\Accounting\SettlementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiptStoreRequest extends FormRequest
{
    use SettlesOpenItems;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'number' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'ledger_id' => ['required', 'exists:ledgers,id'],
            'payment_mode' => ['nullable', Rule::in(PaymentMode::values())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank_account_id' => ['required', 'exists:accounts,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'cheque_no' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp', 'max:10240'],
        ], $this->settlementRules());
    }

    /** A receipt takes money IN, whoever the party is. */
    protected function settlementDirection(): string
    {
        return SettlementService::DIRECTION_IN;
    }

    public function withValidator($validator): void
    {
        $this->validateSettlementSelection($validator);
    }
}

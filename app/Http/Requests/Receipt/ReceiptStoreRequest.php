<?php

namespace App\Http\Requests\Receipt;

use App\Enums\PaymentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiptStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'ledger_id' => ['required', 'exists:ledgers,id'],
            'payment_mode' => ['nullable', Rule::in(PaymentMode::values())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank_account_id' => ['required', 'exists:accounts,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'rate' => ['required', 'numeric', 'min:0'],
            'cheque_no' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.bill_id' => ['required_with:allocations', 'string'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $paymentMode = $this->input('payment_mode', PaymentMode::OnAccount->value);
            $allocations = (array) $this->input('allocations', []);
            $allocatedTotal = collect($allocations)->sum(fn ($allocation) => (float) data_get($allocation, 'amount', 0));
            $amount = (float) $this->input('amount', 0);

            if ($paymentMode === PaymentMode::BillByBill->value && empty($allocations)) {
                $validator->errors()->add('allocations', __('Please select at least one bill.'));
            }

            if ($allocatedTotal - $amount > 0.00001) {
                $validator->errors()->add('allocations', __('The allocated amount cannot exceed the receipt amount.'));
            }
        });
    }
}


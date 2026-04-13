<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['sometimes', 'required', 'integer', 'max:255'],
            'date' => ['sometimes', 'required', 'date'],
            'ledger_id' => ['sometimes', 'required', 'exists:ledgers,id'],
            'payment_mode' => ['sometimes', 'nullable', Rule::in(PaymentMode::values())],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'bank_account_id' => ['sometimes', 'required', 'exists:accounts,id'],
            'currency_id' => ['sometimes', 'required', 'exists:currencies,id'],
            'rate' => ['sometimes', 'required', 'numeric', 'min:0'],
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
                $validator->errors()->add('allocations', __('The allocated amount cannot exceed the payment amount.'));
            }
        });
    }
}


<?php

namespace App\Http\Requests\Hr;

use App\Enums\PaymentMode;
use App\Services\DateConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalaryPaymentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('date')) {
            $this->merge([
                'date' => app(DateConversionService::class)->toGregorian((string) $this->input('date')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', 'exists:employees,id'],
            'payroll_id' => ['nullable', 'string', 'exists:payrolls,id'],
            'date' => ['required', 'date'],
            'currency_id' => ['required', 'string', 'exists:currencies,id'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_mode' => ['nullable', Rule::in(PaymentMode::values())],
            'bank_account_id' => ['required', 'string', 'exists:accounts,id'],
            'cheque_no' => ['nullable', 'string', 'max:100'],
            'narration' => ['nullable', 'string'],

            // Empty means FIFO across whatever is open, which is the right
            // default for "pay this person their salary".
            'allocations' => ['nullable', 'array'],
            'allocations.*.target_line_id' => ['required', 'string'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $allocations = (array) $this->input('allocations', []);

            if ($allocations === []) {
                return;
            }

            $applied = array_sum(array_map(
                fn ($allocation) => (float) ($allocation['amount'] ?? 0),
                $allocations
            ));

            // SettlementService refuses this too, but catching it here gives a
            // field-level error on the form rather than an exception page.
            if ($applied > (float) $this->input('amount') + 0.0001) {
                $validator->errors()->add('allocations', __('hr.applied_exceeds_amount'));
            }

            $targets = collect($allocations)->pluck('target_line_id')->filter();

            if ($targets->count() !== $targets->unique()->count()) {
                $validator->errors()->add('allocations', __('hr.duplicate_allocation_target'));
            }
        });
    }
}

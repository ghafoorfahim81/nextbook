<?php

namespace App\Http\Requests\Hr;

use App\Enums\LoanType;
use App\Services\DateConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeLoanStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $dates = app(DateConversionService::class);

        $this->merge(array_filter([
            'issue_date' => $this->filled('issue_date')
                ? $dates->toGregorian((string) $this->input('issue_date'))
                : null,
            'first_deduction_period' => $this->filled('first_deduction_period')
                ? $dates->toGregorian((string) $this->input('first_deduction_period'))
                : null,
        ], fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', 'exists:employees,id'],
            'loan_type' => ['required', Rule::in(LoanType::values())],
            'currency_id' => ['required', 'string', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'principal_amount' => ['required', 'numeric', 'gt:0'],
            'installment_amount' => ['required', 'numeric', 'gt:0'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:600'],
            'deduct_from_payroll' => ['nullable', 'boolean'],
            'issue_date' => ['required', 'date'],
            'first_deduction_period' => ['nullable', 'date'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bank_account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'remark' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $principal = (float) $this->input('principal_amount');
            $installment = (float) $this->input('installment_amount');

            if ($principal <= 0 || $installment <= 0) {
                return;
            }

            // An instalment larger than the loan is not wrong — the final one
            // is capped at what is owed — but one that would take more than
            // the stated number of instalments means the schedule the employee
            // agreed to is not the schedule that will run.
            if ($installment * (int) $this->input('installments_count') + 0.0001 < $principal) {
                $validator->errors()->add('installment_amount', __('hr.instalments_do_not_cover_loan'));
            }
        });
    }
}

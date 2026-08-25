<?php

namespace App\Http\Requests\Hr;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Http\Requests\Concerns\BranchScopedUnique;
use App\Models\Hr\EmployeeContract;
use App\Services\DateConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeContractStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', $this->existsInBranch('employees')],
            'contract_number' => ['required', 'string', 'max:50', $this->uniqueInBranch('employee_contracts', $this->contractId())],
            'contract_type' => ['required', Rule::in(ContractType::values())],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_current' => ['nullable', 'boolean'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'probation_months' => ['nullable', 'integer', 'min:0', 'max:24'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'working_hours_per_day' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'working_days_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'annual_leave_entitlement' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(ContractStatus::values())],
            'renewed_from_id' => ['nullable', $this->existsInBranch('employee_contracts')],
            'terminated_on' => ['nullable', 'date'],
            'termination_reason' => ['nullable', 'string'],
            'reminder_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'remark' => ['nullable', 'string'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->assertEndDate($validator);
            $this->assertNoOverlap($validator);
        });
    }

    protected function assertEndDate(Validator $validator): void
    {
        $type = ContractType::tryFrom((string) $this->input('contract_type'));
        $end = $this->input('end_date');

        // A fixed-term contract with no end date never triggers a renewal
        // reminder, which is the whole point of tracking contracts.
        if ($type?->requiresEndDate() && ! $end) {
            $validator->errors()->add('end_date', __('hr.validation.end_date_required'));

            return;
        }

        if (! $end) {
            return;
        }

        $dates = app(DateConversionService::class);
        $start = $dates->toGregorian((string) $this->input('start_date'));

        if ($start && $dates->toGregorian((string) $end) <= $start) {
            $validator->errors()->add('end_date', __('hr.validation.end_date_after_start'));
        }
    }

    /**
     * Two live contracts covering the same day make "which terms apply" a
     * coin flip for payroll and leave entitlement.
     */
    protected function assertNoOverlap(Validator $validator): void
    {
        $employeeId = $this->input('employee_id');
        $start = $this->input('start_date');

        if (! $employeeId || ! $start) {
            return;
        }

        $dates = app(DateConversionService::class);
        $start = $dates->toGregorian((string) $start);
        $end = $this->input('end_date') ? $dates->toGregorian((string) $this->input('end_date')) : null;

        $query = EmployeeContract::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [ContractStatus::Active->value, ContractStatus::Draft->value])
            ->where(function ($q) use ($start, $end) {
                // An open-ended existing contract collides with anything that
                // starts on or after it.
                $q->where(function ($inner) use ($start) {
                    $inner->whereNull('end_date')->where('start_date', '<=', $start);
                });

                $q->orWhere(function ($inner) use ($start, $end) {
                    $inner->whereNotNull('end_date')->where('end_date', '>=', $start);

                    if ($end) {
                        $inner->where('start_date', '<=', $end);
                    }
                });
            });

        if ($id = $this->contractId()) {
            $query->where('id', '!=', $id);
        }

        if ($query->exists()) {
            $validator->errors()->add('start_date', __('hr.validation.overlapping_contract'));
        }
    }

    protected function contractId(): ?string
    {
        $contract = $this->route('employee_contract') ?? $this->route('contract');

        if ($contract instanceof EmployeeContract) {
            return (string) $contract->id;
        }

        return $contract ? (string) $contract : null;
    }
}

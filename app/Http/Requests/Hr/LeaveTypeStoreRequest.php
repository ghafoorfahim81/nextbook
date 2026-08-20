<?php

namespace App\Http\Requests\Hr;

use App\Enums\Gender;
use App\Enums\LeaveAccrualMethod;
use App\Http\Requests\Concerns\BranchScopedUnique;
use App\Models\Hr\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LeaveTypeStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->leaveTypeId();

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', $this->uniqueInBranch('leave_types', $id)],
            'colour' => ['nullable', 'string', 'max:20'],
            'is_paid' => ['nullable', 'boolean'],
            'accrual_method' => ['required', Rule::in(LeaveAccrualMethod::values())],
            'days_per_year' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'accrual_rate_per_month' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'max_carry_forward_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'carry_forward_expiry_months' => ['nullable', 'integer', 'min:0', 'max:24'],
            'max_consecutive_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'min_notice_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'min_service_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'applicable_gender' => ['nullable', Rule::in(Gender::values())],
            'requires_attachment' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'deduct_from_salary' => ['nullable', 'boolean'],
            'is_encashable' => ['nullable', 'boolean'],
            'pro_rata_on_join' => ['nullable', 'boolean'],
            'excludes_holidays' => ['nullable', 'boolean'],
            'excludes_weekends' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $method = LeaveAccrualMethod::tryFrom((string) $this->input('accrual_method'));

            // An automatic accrual with no yearly figure would grant zero days
            // forever, which looks like the feature is broken rather than
            // misconfigured.
            if ($method?->isAutomatic() && ! $this->filled('days_per_year')) {
                $validator->errors()->add('days_per_year', __('hr.validation.days_per_year_required'));
            }

            if ($method === LeaveAccrualMethod::MonthlyAccrual && ! $this->filled('accrual_rate_per_month')) {
                $validator->errors()->add('accrual_rate_per_month', __('hr.validation.accrual_rate_required'));
            }

            $carry = $this->input('max_carry_forward_days');
            $perYear = $this->input('days_per_year');

            if ($carry !== null && $carry !== '' && $perYear && (float) $carry > (float) $perYear) {
                $validator->errors()->add('max_carry_forward_days', __('hr.validation.carry_forward_exceeds_entitlement'));
            }
        });
    }

    protected function leaveTypeId(): ?string
    {
        $type = $this->route('leave_type');

        return $type instanceof LeaveType ? (string) $type->id : ($type ? (string) $type : null);
    }
}

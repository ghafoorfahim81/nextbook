<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Concerns\BranchScopedUnique;

use App\Enums\LeaveAllocationSource;
use App\Models\Hr\LeaveAllocation;
use App\Services\DateConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LeaveAllocationStoreRequest extends FormRequest
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
            'leave_type_id' => ['required', $this->existsInBranch('leave_types')],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'entitled_days' => ['required', 'numeric', 'min:0', 'max:365'],
            'carried_forward_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            // Negative allowed: an adjustment is how a correction is recorded,
            // and corrections go both ways.
            'adjustment_days' => ['nullable', 'numeric', 'min:-365', 'max:365'],
            'encashed_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'expired_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'source' => ['nullable', Rule::in(LeaveAllocationSource::values())],
            'remark' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $dates = app(DateConversionService::class);
            $start = $this->input('period_start');
            $end = $this->input('period_end');

            if ($start && $end && $dates->toGregorian((string) $end) <= $dates->toGregorian((string) $start)) {
                $validator->errors()->add('period_end', __('hr.validation.period_end_after_start'));
            }

            // One allocation per employee/type/period. Two would double the
            // entitlement silently.
            $query = LeaveAllocation::query()
                ->where('employee_id', $this->input('employee_id'))
                ->where('leave_type_id', $this->input('leave_type_id'))
                ->whereDate('period_start', $dates->toGregorian((string) $start));

            if ($id = $this->allocationId()) {
                $query->where('id', '!=', $id);
            }

            if ($start && $query->exists()) {
                $validator->errors()->add('period_start', __('hr.validation.duplicate_allocation'));
            }
        });
    }

    protected function allocationId(): ?string
    {
        $allocation = $this->route('leave_allocation');

        return $allocation instanceof LeaveAllocation
            ? (string) $allocation->id
            : ($allocation ? (string) $allocation : null);
    }
}

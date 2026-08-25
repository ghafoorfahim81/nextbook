<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Concerns\BranchScopedUnique;

use App\Enums\EmploymentType;
use App\Enums\PayFrequency;
use App\Enums\PayrollStatus;
use App\Models\Hr\Payroll;
use App\Services\DateConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PayrollStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $dates = app(DateConversionService::class);

        $this->merge(array_filter([
            'period_start' => $this->filled('period_start')
                ? $dates->toGregorian((string) $this->input('period_start'))
                : null,
            'period_end' => $this->filled('period_end')
                ? $dates->toGregorian((string) $this->input('period_end'))
                : null,
            'pay_date' => $this->filled('pay_date')
                ? $dates->toGregorian((string) $this->input('pay_date'))
                : null,
        ], fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:150'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'pay_date' => ['nullable', 'date'],
            'period_label' => ['nullable', 'string', 'max:20'],
            'pay_frequency' => ['required', Rule::in(PayFrequency::values())],
            'currency_id' => ['required', 'string', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric', 'gt:0'],
            // Optional scoping: a run for one department, or for daily-wage
            // staff only.
            'department_id' => ['nullable', 'string', $this->existsInBranch('departments')],
            'employment_type' => ['nullable', Rule::in(EmploymentType::values())],
            'remark' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $start = $this->input('period_start');
            $end = $this->input('period_end');

            if (! $start || ! $end) {
                return;
            }

            // A second LIVE run covering the same people would pay them twice.
            // Reversed and cancelled runs are excluded: re-running a corrected
            // period is exactly the workflow reversal exists for.
            //
            // Scope comparison is deliberately NOT an equality test on
            // department_id. Two runs conflict unless their employee sets are
            // provably disjoint, and a company-wide run (no department, no
            // employment type) overlaps EVERY scoped run. Comparing the
            // columns for equality let a company-wide run and a department run
            // over the same month both pay the same staff.
            $department = $this->input('department_id');
            $employmentType = $this->input('employment_type');

            $overlapping = Payroll::query()
                ->when(
                    $this->route('payroll'),
                    fn ($query) => $query->whereKeyNot($this->route('payroll')->id)
                )
                ->whereNotIn('status', [
                    PayrollStatus::Reversed->value,
                    PayrollStatus::Cancelled->value,
                ])
                ->where('period_start', '<=', $end)
                ->where('period_end', '>=', $start)
                // Disjoint only if BOTH runs name a department and they differ.
                ->when($department, fn ($query) => $query->where(
                    fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $department)
                ))
                // Likewise for employment type.
                ->when($employmentType, fn ($query) => $query->where(
                    fn ($q) => $q->whereNull('employment_type')->orWhere('employment_type', $employmentType)
                ))
                ->first();

            if ($overlapping) {
                $validator->errors()->add('period_start', __('hr.payroll_period_overlaps', [
                    'number' => $overlapping->number,
                ]));
            }
        });
    }
}

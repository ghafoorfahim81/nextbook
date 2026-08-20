<?php

namespace App\Http\Requests\Hr;

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
            'department_id' => ['nullable', 'string', 'exists:departments,id'],
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

            // A second LIVE run over the same period would pay everyone twice.
            // Reversed and cancelled runs are excluded: re-running a corrected
            // period is exactly the workflow reversal exists for.
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
                ->when(
                    $this->filled('department_id'),
                    fn ($query) => $query->where('department_id', $this->input('department_id')),
                    fn ($query) => $query->whereNull('department_id')
                )
                ->exists();

            if ($overlapping) {
                $validator->errors()->add('period_start', __('hr.payroll_period_overlaps'));
            }
        });
    }
}

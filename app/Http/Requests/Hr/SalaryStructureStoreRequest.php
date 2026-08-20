<?php

namespace App\Http\Requests\Hr;

use App\Enums\ComponentCalculationType;
use App\Enums\PayFrequency;
use App\Services\DateConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalaryStructureStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Jalali in, Gregorian stored. toGregorian() is idempotent, so a form
     * that already sends Gregorian is unaffected.
     */
    protected function prepareForValidation(): void
    {
        $dates = app(DateConversionService::class);

        $this->merge(array_filter([
            'effective_from' => $this->filled('effective_from')
                ? $dates->toGregorian((string) $this->input('effective_from'))
                : null,
            'effective_to' => $this->filled('effective_to')
                ? $dates->toGregorian((string) $this->input('effective_to'))
                : null,
        ], fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            'employee_id' => ['nullable', 'string', 'exists:employees,id'],
            'designation_id' => ['nullable', 'string', 'exists:designations,id'],
            'department_id' => ['nullable', 'string', 'exists:departments,id'],
            'currency_id' => ['required', 'string', 'exists:currencies,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'pay_frequency' => ['required', Rule::in(PayFrequency::values())],
            'expense_account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],

            'lines' => ['nullable', 'array'],
            'lines.*.salary_component_id' => ['required', 'string', 'exists:salary_components,id'],
            'lines.*.calculation_type' => ['nullable', Rule::in(ComponentCalculationType::values())],
            'lines.*.amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.sequence' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // A structure attached to nobody and no grade would never be
            // picked up by a payroll run — it would look saved but do nothing.
            if (! $this->filled('employee_id')
                && ! $this->filled('designation_id')
                && ! $this->filled('department_id')) {
                $validator->errors()->add('employee_id', __('hr.structure_needs_a_target'));
            }

            // The same component twice would be applied twice, silently
            // doubling an allowance.
            $ids = collect((array) $this->input('lines', []))
                ->pluck('salary_component_id')
                ->filter();

            if ($ids->count() !== $ids->unique()->count()) {
                $validator->errors()->add('lines', __('hr.duplicate_component_in_structure'));
            }
        });
    }
}

<?php

namespace App\Http\Requests\Hr;

use App\Enums\ComponentCalculationType;
use App\Enums\SalaryComponentType;
use App\Http\Requests\Concerns\BranchScopedUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalaryComponentStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    protected function componentId(): ?string
    {
        return $this->route('salary_component')?->id;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', $this->uniqueInBranch('salary_components', $this->componentId())],
            'component_type' => ['required', Rule::in(SalaryComponentType::values())],
            'calculation_type' => ['required', Rule::in(ComponentCalculationType::values())],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_taxable' => ['nullable', 'boolean'],
            'affects_gross' => ['nullable', 'boolean'],
            'is_prorated' => ['nullable', 'boolean'],
            'account_id' => ['nullable', 'string', $this->existsInBranch('accounts')],
            'sequence' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $calculation = ComponentCalculationType::tryFrom((string) $this->input('calculation_type'));

            // A percentage component with no percentage computes to zero on
            // every payslip forever, which reads as a broken payroll rather
            // than a half-filled form.
            $needsPercentage = in_array($calculation, [
                ComponentCalculationType::PercentOfBasic,
                ComponentCalculationType::PercentOfGross,
            ], true);

            if ($needsPercentage && ! $this->filled('percentage')) {
                $validator->errors()->add('percentage', __('validation.required', [
                    'attribute' => __('hr.percentage'),
                ]));
            }

            $needsAmount = in_array($calculation, [
                ComponentCalculationType::Fixed,
                ComponentCalculationType::PerDay,
                ComponentCalculationType::PerHour,
            ], true);

            if ($needsAmount && ! $this->filled('amount')) {
                $validator->errors()->add('amount', __('validation.required', [
                    'attribute' => __('hr.amount'),
                ]));
            }
        });
    }
}

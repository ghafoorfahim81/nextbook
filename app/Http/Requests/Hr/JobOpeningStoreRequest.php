<?php

namespace App\Http\Requests\Hr;

use App\Enums\EmploymentType;
use App\Http\Requests\Concerns\BranchScopedUnique;
use App\Services\DateConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class JobOpeningStoreRequest extends FormRequest
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
            'posted_date' => $this->filled('posted_date')
                ? $dates->toGregorian((string) $this->input('posted_date'))
                : null,
            'closing_date' => $this->filled('closing_date')
                ? $dates->toGregorian((string) $this->input('closing_date'))
                : null,
        ], fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        $id = $this->route('job_opening')?->id;

        return [
            'code' => ['required', 'string', 'max:50', $this->uniqueInBranch('job_openings', $id)],
            'title' => ['required', 'string', 'max:150'],
            'department_id' => ['nullable', 'string', $this->existsInBranch('departments')],
            'designation_id' => ['nullable', 'string', $this->existsInBranch('designations')],
            'employment_type' => ['required', Rule::in(EmploymentType::values())],
            'vacancies' => ['required', 'integer', 'min:1', 'max:9999'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'responsibilities' => ['nullable', 'string'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'location' => ['nullable', 'string', 'max:150'],
            'posted_date' => ['nullable', 'date'],
            'closing_date' => ['nullable', 'date', 'after_or_equal:posted_date'],
            'hiring_manager_id' => ['nullable', 'string', $this->existsInBranch('employees')],
            'remark' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('min_salary') || ! $this->filled('max_salary')) {
                return;
            }

            if ((float) $this->input('min_salary') > (float) $this->input('max_salary')) {
                $validator->errors()->add('max_salary', __('hr.max_salary_below_min'));
            }
        });
    }
}

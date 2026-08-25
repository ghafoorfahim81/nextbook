<?php

namespace App\Http\Requests\Hr;

use App\Enums\ApplicationSource;
use App\Enums\Gender;
use App\Http\Requests\Concerns\BranchScopedUnique;
use App\Models\Hr\JobOpening;
use App\Services\DateConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class JobApplicationStoreRequest extends FormRequest
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
            'date_of_birth' => $this->filled('date_of_birth')
                ? $dates->toGregorian((string) $this->input('date_of_birth'))
                : null,
            'applied_date' => $this->filled('applied_date')
                ? $dates->toGregorian((string) $this->input('applied_date'))
                : null,
        ], fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        $id = $this->route('job_application')?->id;

        return [
            'job_opening_id' => ['required', 'string', $this->existsInBranch('job_openings')],
            'application_number' => [
                'required', 'string', 'max:50',
                $this->uniqueInBranch('job_applications', $id, 'application_number'),
            ],
            'full_name' => ['required', 'string', 'max:150'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'gender' => ['nullable', Rule::in(Gender::values())],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'province_id' => ['nullable', 'string', 'exists:provinces,id'],
            'current_employer' => ['nullable', 'string', 'max:150'],
            'current_position' => ['nullable', 'string', 'max:150'],
            'years_of_experience' => ['nullable', 'numeric', 'min:0', 'max:70'],
            'highest_education' => ['nullable', 'string', 'max:150'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'source' => ['required', Rule::in(ApplicationSource::values())],
            'referred_by' => ['nullable', 'string', 'max:150'],
            'applied_date' => ['nullable', 'date'],
            'remark' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Only on create: an existing candidate stays attached to their
            // opening even after it closes, or their record would become
            // uneditable the moment the advert came down.
            if ($this->route('job_application')) {
                return;
            }

            $opening = JobOpening::query()->find($this->input('job_opening_id'));

            if ($opening && ! $opening->statusEnum()->acceptsApplications()) {
                $validator->errors()->add('job_opening_id', __('hr.opening_not_accepting_applications'));
            }
        });
    }
}

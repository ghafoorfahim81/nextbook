<?php

namespace App\Http\Requests\Hr;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\PaymentMode;
use App\Http\Requests\Concerns\BranchScopedUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * An empty salary box means "not set yet", not an error.
     *
     * ConvertEmptyStringsToNull turns the blank input into null, and
     * `basic_salary` is a NOT NULL column defaulting to 0, so passing the null
     * through blew up on insert. Zero is what the column already means by
     * unset — the salary structure is authoritative anyway.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('basic_salary') === null || $this->input('basic_salary') === '') {
            $this->merge(['basic_salary' => 0]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', $this->uniqueInBranch('employees')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'grand_father_name' => ['nullable', 'string', 'max:100'],

            'gender' => ['nullable', Rule::in(Gender::values())],
            'marital_status' => ['nullable', Rule::in(MaritalStatus::values())],
            'date_of_birth' => ['nullable', 'date'],

            'national_id' => ['nullable', 'string', 'max:50', $this->uniqueInBranch('employees')],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'tin' => ['nullable', 'string', 'max:50'],

            'country_id' => ['nullable', 'exists:countries,id'],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'blood_group' => ['nullable', 'string', 'max:5'],

            'phone_number' => ['nullable', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],

            'present_address' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],

            'photo' => ['nullable', 'image', 'max:5120'],

            // One employee record per login, or self-service check-in cannot
            // tell which employee a user is.
            'user_id' => ['nullable', 'exists:users,id', $this->uniqueInBranch('employees')],
            'department_id' => ['nullable', $this->existsInBranch('departments')],
            'designation_id' => ['nullable', $this->existsInBranch('designations')],
            'reports_to_id' => ['nullable', $this->existsInBranch('employees')],

            'employment_type' => ['required', Rule::in(EmploymentType::values())],
            'employment_status' => ['required', Rule::in(EmploymentStatus::values())],

            'joining_date' => ['required', 'date'],
            'probation_end_date' => ['nullable', 'date'],
            'confirmation_date' => ['nullable', 'date'],
            'separation_date' => ['nullable', 'date'],
            'separation_reason' => ['nullable', 'string'],

            'currency_id' => ['nullable', 'exists:currencies,id'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],

            'payment_method' => ['nullable', Rule::in(PaymentMode::values())],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_title' => ['nullable', 'string', 'max:150'],
            'iban' => ['nullable', 'string', 'max:50'],

            'is_tax_exempt' => ['nullable', 'boolean'],
            'self_service_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],

            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->assertDateOrder($validator);
            $this->assertSeparationConsistency($validator);
        });
    }

    /**
     * Dates that run backwards produce silently wrong service length, leave
     * pro-rata and payroll proration, so they are rejected rather than stored.
     */
    protected function assertDateOrder(Validator $validator): void
    {
        $joining = $this->date('joining_date');

        if (! $joining) {
            return;
        }

        foreach (['probation_end_date', 'confirmation_date', 'separation_date'] as $field) {
            $value = $this->date($field);

            if ($value && $value->lt($joining)) {
                $validator->errors()->add($field, __('hr.validation.after_joining_date'));
            }
        }

        $dob = $this->date('date_of_birth');

        if ($dob && $dob->gte($joining)) {
            $validator->errors()->add('date_of_birth', __('hr.validation.dob_before_joining'));
        }
    }

    /**
     * A separated employee without a separation date drops out of every
     * "who left and when" report, and an active one carrying a separation date
     * would still be paid.
     */
    protected function assertSeparationConsistency(Validator $validator): void
    {
        $status = EmploymentStatus::tryFrom((string) $this->input('employment_status'));

        if (! $status) {
            return;
        }

        if ($status->isSeparated() && ! $this->input('separation_date')) {
            $validator->errors()->add('separation_date', __('hr.validation.separation_date_required'));
        }

        if ($status->isEmployed() && $this->input('separation_date')) {
            $validator->errors()->add('separation_date', __('hr.validation.separation_date_not_allowed'));
        }
    }
}

<?php

namespace App\Http\Requests\Hr;

use App\Enums\EmployeeDocumentType;
use App\Services\DateConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeDocumentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'document_type' => ['required', Rule::in(EmployeeDocumentType::values())],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issued_by' => ['nullable', 'string', 'max:150'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'is_verified' => ['nullable', 'boolean'],
            'reminder_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'remark' => ['nullable', 'string'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $issue = $this->input('issue_date');
            $expiry = $this->input('expiry_date');

            if (! $issue || ! $expiry) {
                return;
            }

            $dates = app(DateConversionService::class);

            if ($dates->toGregorian((string) $expiry) <= $dates->toGregorian((string) $issue)) {
                $validator->errors()->add('expiry_date', __('hr.validation.expiry_after_issue'));
            }
        });
    }
}

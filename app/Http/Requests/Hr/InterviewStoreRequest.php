<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Concerns\BranchScopedUnique;

use App\Enums\InterviewType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InterviewStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_application_id' => ['required', 'string', $this->existsInBranch('job_applications')],
            // Omitted means "next round", derived by the service. Supplying it
            // is allowed so a mis-scheduled round can be corrected.
            'round' => ['nullable', 'integer', 'min:1', 'max:20'],
            'interview_type' => ['required', Rule::in(InterviewType::values())],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:500'],
            'remark' => ['nullable', 'string'],

            'panelists' => ['nullable', 'array'],
            'panelists.*.employee_id' => ['nullable', 'string', $this->existsInBranch('employees')],
            'panelists.*.user_id' => ['nullable', 'string', 'exists:users,id'],
            'panelists.*.role' => ['nullable', 'string', 'max:100'],
            'panelists.*.is_lead' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = InterviewType::tryFrom((string) $this->input('interview_type'));

            // A video interview with no link is a meeting nobody can attend.
            if ($type === InterviewType::Video && ! $this->filled('meeting_link')) {
                $validator->errors()->add('meeting_link', __('validation.required', [
                    'attribute' => __('hr.meeting_link'),
                ]));
            }

            $panelists = collect((array) $this->input('panelists', []));

            // A panelist row identifying nobody cannot be notified or asked
            // for feedback.
            foreach ($panelists as $index => $panelist) {
                if (empty($panelist['employee_id']) && empty($panelist['user_id'])) {
                    $validator->errors()->add(
                        "panelists.{$index}.employee_id",
                        __('hr.panelist_needs_a_person')
                    );
                }
            }

            $employeeIds = $panelists->pluck('employee_id')->filter();

            // The same person twice would let one opinion be counted twice in
            // the panel verdict.
            if ($employeeIds->count() !== $employeeIds->unique()->count()) {
                $validator->errors()->add('panelists', __('hr.duplicate_panelist'));
            }
        });
    }
}

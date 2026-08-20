<?php

namespace App\Http\Requests\Hr;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The bulk roster grid payload.
 *
 * Errors are keyed `rows.{i}.{field}` because that is the shape the repeater
 * table already renders — the same convention the journal entry form uses.
 */
class AttendanceRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.employee_id' => ['required', 'exists:employees,id'],
            'rows.*.status' => ['required', Rule::in(AttendanceStatus::values())],
            'rows.*.shift_id' => ['nullable', 'exists:shifts,id'],
            'rows.*.check_in' => ['nullable', 'date_format:H:i'],
            'rows.*.check_out' => ['nullable', 'date_format:H:i'],
            'rows.*.overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'rows.*.remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('rows', []) as $i => $row) {
                $status = AttendanceStatus::tryFrom((string) ($row['status'] ?? ''));

                if (! $status) {
                    continue;
                }

                $in = $row['check_in'] ?? null;
                $out = $row['check_out'] ?? null;

                // A present day with no arrival time records zero worked hours,
                // which then prorates pay down without anyone noticing.
                if (in_array($status, [AttendanceStatus::Present, AttendanceStatus::Late], true) && ! $in) {
                    $validator->errors()->add("rows.{$i}.check_in", __('hr.validation.check_in_required'));
                }

                // An absent day with times on it is contradictory; one of the
                // two was meant and we cannot tell which.
                if ($status === AttendanceStatus::Absent && ($in || $out)) {
                    $validator->errors()->add("rows.{$i}.status", __('hr.validation.absent_with_times'));
                }
            }
        });
    }

    /**
     * Duplicate employees in one grid would make the last row silently win.
     */
    protected function prepareForValidation(): void
    {
        $rows = (array) $this->input('rows', []);
        $seen = [];

        foreach ($rows as $row) {
            $id = $row['employee_id'] ?? null;

            if ($id && isset($seen[$id])) {
                abort(422, __('hr.validation.duplicate_roster_employee'));
            }

            if ($id) {
                $seen[$id] = true;
            }
        }
    }
}

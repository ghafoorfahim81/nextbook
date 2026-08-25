<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Concerns\BranchScopedUnique;
use App\Models\Hr\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ShiftStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->shiftId();

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', $this->uniqueInBranch('shifts', $id)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'crosses_midnight' => ['nullable', 'boolean'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'grace_in_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'grace_out_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'full_day_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'half_day_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'min:1', 'max:7'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            if (! $start || ! $end) {
                return;
            }

            // An end before the start is only meaningful for a night shift.
            // Without the flag it is a typo that would produce negative hours.
            if ($end <= $start && ! $this->boolean('crosses_midnight')) {
                $validator->errors()->add('end_time', __('hr.validation.end_time_after_start'));
            }

            $half = $this->input('half_day_hours');
            $full = $this->input('full_day_hours');

            if ($half !== null && $half !== '' && $full && (float) $half >= (float) $full) {
                $validator->errors()->add('half_day_hours', __('hr.validation.half_day_less_than_full'));
            }
        });
    }

    protected function shiftId(): ?string
    {
        $shift = $this->route('shift');

        return $shift instanceof Shift ? (string) $shift->id : ($shift ? (string) $shift : null);
    }
}

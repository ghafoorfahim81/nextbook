<?php

namespace App\Http\Requests\Hr;

use App\Enums\HalfDayPeriod;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Services\DateConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LeaveRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date'],
            'is_half_day' => ['nullable', 'boolean'],
            'half_day_period' => ['nullable', Rule::in(HalfDayPeriod::values())],
            'reason' => ['nullable', 'string'],
            'contact_during_leave' => ['nullable', 'string', 'max:50'],
            'handover_to_id' => ['nullable', 'exists:employees,id'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->assertDateOrder($validator);
            $this->assertHalfDayIsOneDay($validator);
            $this->assertNoOverlap($validator);
            $this->assertTypeRules($validator);
        });
    }

    private function assertDateOrder(Validator $validator): void
    {
        [$from, $to] = $this->range();

        if ($from && $to && $to->lt($from)) {
            $validator->errors()->add('to_date', __('hr.validation.to_date_after_from'));
        }
    }

    /**
     * Half a day is half of ONE day. A half-day spanning a week is a data-entry
     * error that would otherwise be stored as 0.5 days off.
     */
    private function assertHalfDayIsOneDay(Validator $validator): void
    {
        if (! $this->boolean('is_half_day')) {
            return;
        }

        [$from, $to] = $this->range();

        if ($from && $to && ! $from->isSameDay($to)) {
            $validator->errors()->add('is_half_day', __('hr.validation.half_day_single_date'));
        }

        if (! $this->input('half_day_period')) {
            $validator->errors()->add('half_day_period', __('hr.validation.half_day_period_required'));
        }
    }

    private function assertNoOverlap(Validator $validator): void
    {
        [$from, $to] = $this->range();
        $employeeId = $this->input('employee_id');

        if (! $from || ! $to || ! $employeeId) {
            return;
        }

        $query = LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->blocking()
            ->overlapping($from->toDateString(), $to->toDateString());

        if ($id = $this->leaveRequestId()) {
            $query->where('id', '!=', $id);
        }

        if ($query->exists()) {
            $validator->errors()->add('from_date', __('hr.validation.overlapping_leave'));
        }
    }

    /**
     * The leave type's own policy: who may take it, how much notice, how long,
     * and whether proof is required.
     */
    private function assertTypeRules(Validator $validator): void
    {
        $type = LeaveType::find($this->input('leave_type_id'));
        $employee = Employee::find($this->input('employee_id'));
        [$from, $to] = $this->range();

        if (! $type || ! $employee || ! $from || ! $to) {
            return;
        }

        if ($type->applicable_gender && $employee->gender !== $type->applicable_gender) {
            $validator->errors()->add('leave_type_id', __('hr.validation.leave_type_gender'));
        }

        if ($type->min_service_months) {
            $eligibleFrom = $employee->joining_date?->copy()->addMonths($type->min_service_months);

            if (! $eligibleFrom || $eligibleFrom->gt($from)) {
                $validator->errors()->add('leave_type_id', __('hr.validation.leave_type_min_service', [
                    'months' => $type->min_service_months,
                ]));
            }
        }

        if ($type->min_notice_days && ! $this->boolean('skip_notice_check')) {
            $earliest = Carbon::today()->addDays($type->min_notice_days);

            if ($from->lt($earliest)) {
                $validator->errors()->add('from_date', __('hr.validation.leave_min_notice', [
                    'days' => $type->min_notice_days,
                ]));
            }
        }

        if ($type->max_consecutive_days) {
            $span = $from->diffInDays($to) + 1;

            if ($span > $type->max_consecutive_days) {
                $validator->errors()->add('to_date', __('hr.validation.leave_max_consecutive', [
                    'days' => $type->max_consecutive_days,
                ]));
            }
        }

        if ($type->requires_attachment && ! $this->hasFile('documents') && ! $this->leaveRequestId()) {
            $validator->errors()->add('documents', __('hr.validation.leave_requires_attachment'));
        }
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function range(): array
    {
        $dates = app(DateConversionService::class);

        $from = $this->input('from_date');
        $to = $this->input('to_date');

        return [
            $from ? Carbon::parse($dates->toGregorian((string) $from)) : null,
            $to ? Carbon::parse($dates->toGregorian((string) $to)) : null,
        ];
    }

    protected function leaveRequestId(): ?string
    {
        $request = $this->route('leave_request');

        return $request instanceof LeaveRequest ? (string) $request->id : ($request ? (string) $request : null);
    }
}

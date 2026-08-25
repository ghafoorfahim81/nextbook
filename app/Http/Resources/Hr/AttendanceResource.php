<?php

namespace App\Http\Resources\Hr;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof AttendanceStatus
            ? $this->status
            : AttendanceStatus::tryFrom((string) $this->status);

        $source = $this->source instanceof AttendanceSource
            ? $this->source
            : AttendanceSource::tryFrom((string) $this->source);

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->full_name,
            'employee_code' => $this->employee?->code,
            'department_name' => $this->employee?->department?->name,
            'date' => app(DateConversionService::class)->toDisplay($this->date),
            'shift_id' => $this->shift_id,
            'shift_name' => $this->shift?->name,
            'check_in' => $this->check_in?->format('H:i'),
            'check_out' => $this->check_out?->format('H:i'),
            'worked_hours' => (float) $this->worked_hours,
            'overtime_hours' => (float) $this->overtime_hours,
            'break_minutes' => (int) $this->break_minutes,
            'late_minutes' => (int) $this->late_minutes,
            'early_out_minutes' => (int) $this->early_out_minutes,
            'status' => $status?->value,
            'status_label' => $status?->getLabel(),
            'source' => $source?->value,
            'source_label' => $source?->getLabel(),
            'needs_review' => (bool) $this->needs_review,
            'is_locked' => $this->isLocked(),
            'leave_request_id' => $this->leave_request_id,
            'remark' => $this->remark,
        ];
    }
}

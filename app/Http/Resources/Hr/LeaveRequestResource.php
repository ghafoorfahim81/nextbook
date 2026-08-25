<?php

namespace App\Http\Resources\Hr;

use App\Enums\HalfDayPeriod;
use App\Enums\LeaveRequestStatus;
use App\Http\Resources\AttachmentResource;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);

        $status = $this->status instanceof LeaveRequestStatus
            ? $this->status
            : LeaveRequestStatus::tryFrom((string) $this->status);

        $half = $this->half_day_period instanceof HalfDayPeriod
            ? $this->half_day_period
            : HalfDayPeriod::tryFrom((string) $this->half_day_period);

        return [
            'id' => $this->id,
            'number' => $this->number,

            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->full_name,
            'employee_code' => $this->employee?->code,

            'leave_type_id' => $this->leave_type_id,
            'leave_type_name' => $this->leaveType?->name,
            'leave_type_colour' => $this->leaveType?->colour,
            'is_paid' => (bool) ($this->leaveType?->is_paid ?? true),

            'from_date' => $dates->toDisplay($this->from_date),
            'to_date' => $dates->toDisplay($this->to_date),
            'is_half_day' => (bool) $this->is_half_day,
            'half_day_period' => $half?->value,
            'half_day_period_label' => $half?->getLabel(),
            'days' => (float) $this->days,

            'reason' => $this->reason,
            'contact_during_leave' => $this->contact_during_leave,
            'handover_to_id' => $this->handover_to_id,
            'handover_to_name' => $this->handoverTo?->full_name,

            'status' => $status?->value,
            'status_label' => $status?->getLabel(),
            // Drives which action buttons the Show page renders, so the UI does
            // not have to reimplement the state machine.
            'allowed_transitions' => $status?->allowedTransitions() ?? [],

            'applied_at' => $this->applied_at?->toDateTimeString(),
            'approved_by' => $this->approver?->name,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'rejected_at' => $this->rejected_at?->toDateTimeString(),
            'rejection_reason' => $this->rejection_reason,
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),

            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),

            'created_by' => $this->createdBy?->name,
        ];
    }
}

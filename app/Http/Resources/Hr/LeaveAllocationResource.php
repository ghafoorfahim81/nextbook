<?php

namespace App\Http\Resources\Hr;

use App\Enums\LeaveAllocationSource;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);

        $source = $this->source instanceof LeaveAllocationSource
            ? $this->source
            : LeaveAllocationSource::tryFrom((string) $this->source);

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->full_name,
            'employee_code' => $this->employee?->code,
            'leave_type_id' => $this->leave_type_id,
            'leave_type_name' => $this->leaveType?->name,
            'period_start' => $dates->toDisplay($this->period_start),
            'period_end' => $dates->toDisplay($this->period_end),
            'entitled_days' => (float) $this->entitled_days,
            'carried_forward_days' => (float) $this->carried_forward_days,
            'adjustment_days' => (float) $this->adjustment_days,
            'encashed_days' => (float) $this->encashed_days,
            'expired_days' => (float) $this->expired_days,
            'granted_days' => $this->grantedDays(),
            'source' => $source?->value,
            'source_label' => $source?->getLabel(),
            'remark' => $this->remark,
            'created_by' => $this->createdBy?->name,
        ];
    }
}

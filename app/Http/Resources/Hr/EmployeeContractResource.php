<?php

namespace App\Http\Resources\Hr;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Http\Resources\AttachmentResource;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);

        $type = $this->contract_type instanceof ContractType
            ? $this->contract_type
            : ContractType::tryFrom((string) $this->contract_type);
        $status = $this->status instanceof ContractStatus
            ? $this->status
            : ContractStatus::tryFrom((string) $this->status);

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->full_name,
            'employee_code' => $this->employee?->code,

            'contract_number' => $this->contract_number,
            'contract_type' => $type?->value,
            'contract_type_label' => $type?->getLabel(),

            'start_date' => $dates->toDisplay($this->start_date),
            'end_date' => $dates->toDisplay($this->end_date),
            'is_current' => (bool) $this->is_current,

            'basic_salary' => (float) $this->basic_salary,
            'currency_id' => $this->currency_id,
            'currency_code' => $this->currency?->code,

            'probation_months' => $this->probation_months,
            'notice_period_days' => $this->notice_period_days,
            'working_hours_per_day' => (float) $this->working_hours_per_day,
            'working_days_per_week' => $this->working_days_per_week,
            'annual_leave_entitlement' => $this->annual_leave_entitlement !== null
                ? (float) $this->annual_leave_entitlement
                : null,

            'status' => $status?->value,
            'status_label' => $status?->getLabel(),

            'renewed_from_id' => $this->renewed_from_id,
            'terminated_on' => $dates->toDisplay($this->terminated_on),
            'termination_reason' => $this->termination_reason,

            'reminder_days_before' => $this->reminder_days_before,
            // Raw integer rather than a formatted string: the UI decides
            // whether to render "in 12 days" or an amber badge.
            'days_until_expiry' => $this->daysUntilExpiry(),
            'remark' => $this->remark,

            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),

            'created_by' => $this->createdBy?->name,
            'updated_by' => $this->updatedBy?->name,
        ];
    }
}

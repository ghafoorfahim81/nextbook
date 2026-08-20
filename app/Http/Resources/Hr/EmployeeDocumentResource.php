<?php

namespace App\Http\Resources\Hr;

use App\Enums\EmployeeDocumentType;
use App\Http\Resources\AttachmentResource;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);

        $type = $this->document_type instanceof EmployeeDocumentType
            ? $this->document_type
            : EmployeeDocumentType::tryFrom((string) $this->document_type);

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->full_name,
            'employee_code' => $this->employee?->code,

            'document_type' => $type?->value,
            'document_type_label' => $type?->getLabel(),
            'document_number' => $this->document_number,
            'issued_by' => $this->issued_by,

            'issue_date' => $dates->toDisplay($this->issue_date),
            'expiry_date' => $dates->toDisplay($this->expiry_date),
            'days_until_expiry' => $this->daysUntilExpiry(),
            'is_expired' => $this->isExpired(),

            'is_verified' => (bool) $this->is_verified,
            'verified_by' => $this->verifiedBy?->name,
            'verified_at' => $this->verified_at?->toDateTimeString(),

            'reminder_days_before' => $this->reminder_days_before,
            'remark' => $this->remark,

            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),

            'created_by' => $this->createdBy?->name,
            'updated_by' => $this->updatedBy?->name,
        ];
    }
}

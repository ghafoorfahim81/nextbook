<?php

namespace App\Http\Resources\Hr;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The employee list row. Deliberately lean — the Show page uses EmployeeResource.
 */
class EmployeeListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->employment_type instanceof EmploymentType
            ? $this->employment_type
            : EmploymentType::tryFrom((string) $this->employment_type);

        $status = $this->employment_status instanceof EmploymentStatus
            ? $this->employment_status
            : EmploymentStatus::tryFrom((string) $this->employment_status);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'full_name' => $this->full_name,
            'father_name' => $this->father_name,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'photo_url' => $this->photo_url,

            'department_id' => $this->department_id,
            'department_name' => $this->department?->name,
            'designation_id' => $this->designation_id,
            'designation_name' => $this->designation?->name,
            'manager_name' => $this->manager?->full_name,

            'employment_type' => $type?->value,
            'employment_type_label' => $type?->getLabel(),
            'employment_status' => $status?->value,
            'employment_status_label' => $status?->getLabel(),

            'joining_date' => app(DateConversionService::class)->toDisplay($this->joining_date),
            'basic_salary' => (float) $this->basic_salary,
            'currency_code' => $this->currency?->code,
            'is_active' => (bool) $this->is_active,

            'created_by' => $this->createdBy?->name,
        ];
    }
}

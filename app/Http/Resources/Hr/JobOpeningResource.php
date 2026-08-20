<?php

namespace App\Http\Resources\Hr;

use App\Enums\EmploymentType;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobOpeningResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);
        $status = $this->statusEnum();

        $employmentType = $this->employment_type instanceof EmploymentType
            ? $this->employment_type
            : EmploymentType::tryFrom((string) $this->employment_type);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'department_id' => $this->department_id,
            'department_name' => $this->whenLoaded('department', fn () => $this->department?->name),
            'designation_id' => $this->designation_id,
            'designation_name' => $this->whenLoaded('designation', fn () => $this->designation?->name),
            'employment_type' => $employmentType?->value,
            'employment_type_label' => $employmentType?->getLabel(),
            'vacancies' => (int) $this->vacancies,
            'remaining_vacancies' => $this->remainingVacancies(),
            'description' => $this->description,
            'requirements' => $this->requirements,
            'responsibilities' => $this->responsibilities,
            'min_salary' => $this->min_salary !== null ? (float) $this->min_salary : null,
            'max_salary' => $this->max_salary !== null ? (float) $this->max_salary : null,
            'currency_id' => $this->currency_id,
            'currency_code' => $this->whenLoaded('currency', fn () => $this->currency?->code),
            'location' => $this->location,
            'posted_date' => $dates->toDisplay($this->posted_date?->toDateString()),
            'closing_date' => $dates->toDisplay($this->closing_date?->toDateString()),
            'status' => $status->value,
            'status_label' => $status->getLabel(),
            'allowed_transitions' => $status->allowedTransitions(),
            'accepts_applications' => $status->acceptsApplications(),
            'hiring_manager_id' => $this->hiring_manager_id,
            'hiring_manager_name' => $this->whenLoaded(
                'hiringManager',
                fn () => $this->hiringManager?->full_name
            ),
            'application_count' => $this->whenCounted('applications'),
            'remark' => $this->remark,
            'created_by' => $this->createdBy?->name,
        ];
    }
}

<?php

namespace App\Http\Resources\Hr;

use App\Enums\ApplicationSource;
use App\Enums\Gender;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);
        $status = $this->statusEnum();

        $gender = $this->gender instanceof Gender
            ? $this->gender
            : Gender::tryFrom((string) $this->gender);

        $source = $this->source instanceof ApplicationSource
            ? $this->source
            : ApplicationSource::tryFrom((string) $this->source);

        return [
            'id' => $this->id,
            'job_opening_id' => $this->job_opening_id,
            'job_opening_title' => $this->whenLoaded('opening', fn () => $this->opening?->title),
            'job_opening_code' => $this->whenLoaded('opening', fn () => $this->opening?->code),
            'application_number' => $this->application_number,
            'full_name' => $this->full_name,
            'father_name' => $this->father_name,
            'gender' => $gender?->value,
            'gender_label' => $gender?->getLabel(),
            'date_of_birth' => $dates->toDisplay($this->date_of_birth?->toDateString()),
            'national_id' => $this->national_id,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'address' => $this->address,
            'province_id' => $this->province_id,
            'province_name' => $this->whenLoaded('province', fn () => $this->province?->localized_name),
            'current_employer' => $this->current_employer,
            'current_position' => $this->current_position,
            'years_of_experience' => $this->years_of_experience !== null
                ? (float) $this->years_of_experience
                : null,
            'highest_education' => $this->highest_education,
            'expected_salary' => $this->expected_salary !== null ? (float) $this->expected_salary : null,
            'currency_id' => $this->currency_id,
            'notice_period_days' => $this->notice_period_days,
            'source' => $source?->value,
            'source_label' => $source?->getLabel(),
            'referred_by' => $this->referred_by,
            'status' => $status->value,
            'status_label' => $status->getLabel(),
            'allowed_transitions' => $status->allowedTransitions(),
            'can_be_interviewed' => $status->canBeInterviewed(),
            'is_active' => $status->isActive(),
            'score' => $this->score !== null ? (float) $this->score : null,
            'rejection_reason' => $this->rejection_reason,
            'applied_date' => $dates->toDisplay($this->applied_date?->toDateString()),
            'offered_date' => $dates->toDisplay($this->offered_date?->toDateString()),
            'offered_salary' => $this->offered_salary !== null ? (float) $this->offered_salary : null,
            'hired_employee_id' => $this->hired_employee_id,
            'hired_employee_name' => $this->whenLoaded(
                'hiredEmployee',
                fn () => $this->hiredEmployee?->full_name
            ),
            'interview_count' => $this->whenCounted('interviews'),
            'interviews' => InterviewResource::collection($this->whenLoaded('interviews')),
            'attachments' => $this->whenLoaded('attachments'),
            'remark' => $this->remark,
            'created_by' => $this->createdBy?->name,
        ];
    }
}

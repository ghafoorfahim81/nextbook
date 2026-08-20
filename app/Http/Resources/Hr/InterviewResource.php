<?php

namespace App\Http\Resources\Hr;

use App\Enums\InterviewRecommendation;
use App\Enums\InterviewType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->statusEnum();

        $type = $this->interview_type instanceof InterviewType
            ? $this->interview_type
            : InterviewType::tryFrom((string) $this->interview_type);

        $recommendation = $this->recommendation instanceof InterviewRecommendation
            ? $this->recommendation
            : InterviewRecommendation::tryFrom((string) $this->recommendation);

        $verdict = $this->panelVerdict();

        return [
            'id' => $this->id,
            'job_application_id' => $this->job_application_id,
            'candidate_name' => $this->whenLoaded('application', fn () => $this->application?->full_name),
            'round' => (int) $this->round,
            'interview_type' => $type?->value,
            'interview_type_label' => $type?->getLabel(),
            'is_remote' => (bool) $type?->isRemote(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'duration_minutes' => (int) $this->duration_minutes,
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
            'status' => $status->value,
            'status_label' => $status->getLabel(),
            'accepts_feedback' => $status->acceptsFeedback(),
            'score' => $this->score !== null ? (float) $this->score : null,
            'recommendation' => $recommendation?->value,
            'recommendation_label' => $recommendation?->getLabel(),
            // The panel's verdict, which is NOT an average of recommendations:
            // one strong objection blocks whatever the others said.
            'panel_verdict' => $verdict?->value,
            'panel_verdict_label' => $verdict?->getLabel(),
            'feedback' => $this->feedback,
            'remark' => $this->remark,
            'panelists' => InterviewPanelistResource::collection($this->whenLoaded('panelists')),
            'created_by' => $this->createdBy?->name,
        ];
    }
}

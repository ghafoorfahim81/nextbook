<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewPanelistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $recommendation = $this->recommendationEnum();

        return [
            'id' => $this->id,
            'interview_id' => $this->interview_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee?->full_name),
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'role' => $this->role,
            'is_lead' => (bool) $this->is_lead,
            'score' => $this->score !== null ? (float) $this->score : null,
            'recommendation' => $recommendation?->value,
            'recommendation_label' => $recommendation?->getLabel(),
            'feedback' => $this->feedback,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'has_submitted' => $this->hasSubmitted(),
        ];
    }
}

<?php

namespace App\Http\Requests\Hr;

use App\Enums\InterviewRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InterviewFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'recommendation' => ['nullable', Rule::in(InterviewRecommendation::values())],
            'feedback' => ['nullable', 'string'],
        ];
    }
}

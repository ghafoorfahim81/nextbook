<?php

namespace App\Enums;

/**
 * What an interviewer concluded.
 *
 * Deliberately not a number. A five-point score invites averaging across a
 * panel, and an average hides the case that matters most — one interviewer
 * with a strong objection nobody else saw.
 */
enum InterviewRecommendation: string
{
    case StrongHire = 'strong_hire';
    case Hire = 'hire';
    case Neutral = 'neutral';
    case NoHire = 'no_hire';
    case StrongNoHire = 'strong_no_hire';

    public function getLabel(): string
    {
        return match ($this) {
            self::StrongHire => __('enums.interview_recommendation.strong_hire'),
            self::Hire => __('enums.interview_recommendation.hire'),
            self::Neutral => __('enums.interview_recommendation.neutral'),
            self::NoHire => __('enums.interview_recommendation.no_hire'),
            self::StrongNoHire => __('enums.interview_recommendation.strong_no_hire'),
        };
    }

    public function isPositive(): bool
    {
        return $this === self::StrongHire || $this === self::Hire;
    }

    public function isBlocking(): bool
    {
        return $this === self::StrongNoHire;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

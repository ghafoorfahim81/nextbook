<?php

namespace App\Enums;

/**
 * `no_show` is separate from `cancelled` because they mean opposite things
 * about the candidate, and a hiring manager reviewing the pipeline needs to
 * see which one happened.
 */
enum InterviewStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';
    case NoShow = 'no_show';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => __('enums.interview_status.scheduled'),
            self::Completed => __('enums.interview_status.completed'),
            self::Cancelled => __('enums.interview_status.cancelled'),
            self::Rescheduled => __('enums.interview_status.rescheduled'),
            self::NoShow => __('enums.interview_status.no_show'),
        };
    }

    /** Whether feedback can still be recorded against it. */
    public function acceptsFeedback(): bool
    {
        return $this === self::Scheduled || $this === self::Completed;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

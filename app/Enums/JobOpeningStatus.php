<?php

namespace App\Enums;

/**
 * Where a vacancy is in its life.
 *
 *   draft ──publish──► published ──close──► closed ──► filled
 *     │                    │
 *     └──cancel───────────►└──cancel──► cancelled
 */
enum JobOpeningStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Filled = 'filled';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('enums.job_opening_status.draft'),
            self::Published => __('enums.job_opening_status.published'),
            self::Closed => __('enums.job_opening_status.closed'),
            self::Filled => __('enums.job_opening_status.filled'),
            self::Cancelled => __('enums.job_opening_status.cancelled'),
        };
    }

    /**
     * Whether new applications can still be taken.
     *
     * A CLOSED opening still accepts none but keeps its pipeline moving —
     * closing the door on new candidates is not the same as abandoning the
     * ones already being interviewed.
     */
    public function acceptsApplications(): bool
    {
        return $this === self::Published;
    }

    /** Whether the pipeline behind it is still live. */
    public function isOpen(): bool
    {
        return match ($this) {
            self::Draft, self::Published, self::Closed => true,
            self::Filled, self::Cancelled => false,
        };
    }

    /** @return array<int, string> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published->value, self::Cancelled->value],
            self::Published => [self::Closed->value, self::Filled->value, self::Cancelled->value],
            self::Closed => [self::Published->value, self::Filled->value, self::Cancelled->value],
            self::Filled, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target->value, $this->allowedTransitions(), true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

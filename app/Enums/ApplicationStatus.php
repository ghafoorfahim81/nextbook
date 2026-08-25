<?php

namespace App\Enums;

/**
 * A candidate's progress through the pipeline.
 *
 *   applied ──shortlist──► shortlisted ──► interviewing ──► offered ──► hired
 *      │                        │                │             │
 *      └──reject────────────────┴────────────────┴─────────────┘
 *                                                 └──withdraw (candidate)
 *
 * `withdrawn` is kept distinct from `rejected` on purpose: a candidate who
 * took another job is someone to approach again, and a rejection list that
 * quietly includes them is worse than useless.
 */
enum ApplicationStatus: string
{
    case Applied = 'applied';
    case Shortlisted = 'shortlisted';
    case Interviewing = 'interviewing';
    case Offered = 'offered';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::Applied => __('enums.application_status.applied'),
            self::Shortlisted => __('enums.application_status.shortlisted'),
            self::Interviewing => __('enums.application_status.interviewing'),
            self::Offered => __('enums.application_status.offered'),
            self::Hired => __('enums.application_status.hired'),
            self::Rejected => __('enums.application_status.rejected'),
            self::Withdrawn => __('enums.application_status.withdrawn'),
        };
    }

    /** Whether this candidate is still in the running. */
    public function isActive(): bool
    {
        return match ($this) {
            self::Applied, self::Shortlisted, self::Interviewing, self::Offered => true,
            self::Hired, self::Rejected, self::Withdrawn => false,
        };
    }

    /** Whether an interview can still be scheduled. */
    public function canBeInterviewed(): bool
    {
        return match ($this) {
            self::Shortlisted, self::Interviewing, self::Offered => true,
            default => false,
        };
    }

    /** @return array<int, string> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Applied => [
                self::Shortlisted->value, self::Rejected->value, self::Withdrawn->value,
            ],
            self::Shortlisted => [
                self::Interviewing->value, self::Offered->value,
                self::Rejected->value, self::Withdrawn->value,
            ],
            self::Interviewing => [
                self::Offered->value, self::Rejected->value, self::Withdrawn->value,
            ],
            self::Offered => [
                self::Hired->value, self::Rejected->value, self::Withdrawn->value,
            ],
            self::Hired, self::Rejected, self::Withdrawn => [],
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

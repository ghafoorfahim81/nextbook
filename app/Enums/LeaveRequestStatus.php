<?php

namespace App\Enums;

/**
 * The leave request state machine.
 *
 *   draft ──submit──► pending ──approve──► approved ──cancel──► cancelled
 *     │                  │                     │
 *     │                  └──reject──► rejected └──withdraw──► withdrawn
 *     └──delete (draft only)
 */
enum LeaveRequestStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('enums.leave_request_status.draft'),
            self::Pending => __('enums.leave_request_status.pending'),
            self::Approved => __('enums.leave_request_status.approved'),
            self::Rejected => __('enums.leave_request_status.rejected'),
            self::Cancelled => __('enums.leave_request_status.cancelled'),
            self::Withdrawn => __('enums.leave_request_status.withdrawn'),
        };
    }

    /**
     * Statuses that consume entitlement.
     *
     * Pending is excluded: it is shown separately so an employee can see what
     * is in flight without it being deducted before anyone has agreed to it.
     */
    public function consumesBalance(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Statuses that block an overlapping request. A rejected or cancelled
     * request leaves the dates free again.
     */
    public function blocksOverlap(): bool
    {
        return match ($this) {
            self::Draft, self::Pending, self::Approved => true,
            self::Rejected, self::Cancelled, self::Withdrawn => false,
        };
    }

    /** @return array<int, string> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending->value, self::Cancelled->value],
            self::Pending => [self::Approved->value, self::Rejected->value, self::Cancelled->value],
            self::Approved => [self::Cancelled->value, self::Withdrawn->value],
            self::Rejected, self::Cancelled, self::Withdrawn => [],
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

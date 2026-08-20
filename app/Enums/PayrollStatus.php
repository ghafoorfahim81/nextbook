<?php

namespace App\Enums;

/**
 * A payroll run's lifecycle.
 *
 *   draft ──calculate──► calculated ──submit──► pending_approval ──approve──► approved
 *                             │                                                  │
 *                             └──────────────── recalculate ─────────────────────┘
 *                                                                                │
 *                                                                             post
 *                                                                                ▼
 *                             cancelled ◄──cancel── draft/calculated         posted
 *                                                                                │
 *                                                                     reverse ────┤
 *                                                                                ▼
 *                                                                            reversed
 *
 * Everything up to `approved` is free to recalculate — nothing has touched the
 * general ledger yet. `posted` is immutable: correcting it means a reversal and
 * a fresh run, so the original voucher and the correction both survive.
 */
enum PayrollStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Posted = 'posted';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('enums.payroll_status.draft'),
            self::Calculated => __('enums.payroll_status.calculated'),
            self::PendingApproval => __('enums.payroll_status.pending_approval'),
            self::Approved => __('enums.payroll_status.approved'),
            self::Posted => __('enums.payroll_status.posted'),
            self::Paid => __('enums.payroll_status.paid'),
            self::Cancelled => __('enums.payroll_status.cancelled'),
            self::Reversed => __('enums.payroll_status.reversed'),
        };
    }

    /**
     * Whether the lines can still be thrown away and rebuilt.
     *
     * False from `posted` onward: the GL has the numbers, and silently
     * recomputing them would leave the voucher describing amounts that no
     * longer exist anywhere.
     */
    public function isRecalculable(): bool
    {
        return match ($this) {
            self::Draft, self::Calculated, self::PendingApproval, self::Approved => true,
            self::Posted, self::Paid, self::Cancelled, self::Reversed => false,
        };
    }

    /** Whether this run has a live posting in the general ledger. */
    public function isPosted(): bool
    {
        return $this === self::Posted || $this === self::Paid;
    }

    /** @return array<int, string> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Calculated->value, self::Cancelled->value],
            self::Calculated => [self::PendingApproval->value, self::Draft->value, self::Cancelled->value],
            self::PendingApproval => [self::Approved->value, self::Calculated->value, self::Cancelled->value],
            self::Approved => [self::Posted->value, self::Calculated->value],
            self::Posted => [self::Paid->value, self::Reversed->value],
            // Paid can still be reversed, but only after the disbursement is
            // voided — PayrollService enforces that, not the state machine.
            self::Paid => [self::Reversed->value],
            self::Cancelled, self::Reversed => [],
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

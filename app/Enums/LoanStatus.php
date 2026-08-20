<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Active = 'active';
    case Settled = 'settled';
    case WrittenOff = 'written_off';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('enums.loan_status.draft'),
            self::PendingApproval => __('enums.loan_status.pending_approval'),
            self::Approved => __('enums.loan_status.approved'),
            self::Active => __('enums.loan_status.active'),
            self::Settled => __('enums.loan_status.settled'),
            self::WrittenOff => __('enums.loan_status.written_off'),
            self::Cancelled => __('enums.loan_status.cancelled'),
        };
    }

    /** Whether payroll should deduct an instalment for this loan. */
    public function isRecoverable(): bool
    {
        return $this === self::Active;
    }

    /** Whether the money has left the company and been posted. */
    public function isDisbursed(): bool
    {
        return match ($this) {
            self::Active, self::Settled, self::WrittenOff => true,
            self::Draft, self::PendingApproval, self::Approved, self::Cancelled => false,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

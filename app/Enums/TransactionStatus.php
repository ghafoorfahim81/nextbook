<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case REVERSED = 'reversed';

    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => __('enums.transaction_status.draft'),
            self::POSTED => __('enums.transaction_status.posted'),
            self::APPROVED => __('enums.transaction_status.approved'),
            self::REJECTED => __('enums.transaction_status.rejected'),
            self::CANCELLED => __('enums.transaction_status.cancelled'),
            self::REVERSED => __('enums.transaction_status.reversed'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

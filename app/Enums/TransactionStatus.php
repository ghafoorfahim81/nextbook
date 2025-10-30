<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => __('enums.transaction_status.pending'),
            self::APPROVED => __('enums.transaction_status.approved'),
            self::REJECTED => __('enums.transaction_status.rejected'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

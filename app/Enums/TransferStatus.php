<?php

namespace App\Enums;

enum TransferStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => __('enums.transfer_status.pending'),
            self::COMPLETED => __('enums.transfer_status.completed'),
            self::CANCELLED => __('enums.transfer_status.cancelled'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

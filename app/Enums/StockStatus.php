<?php

namespace App\Enums;

enum StockStatus: string
{
    case POSTED = 'posted';
    case DRAFT = 'draft';
    case VOIDED = 'voided';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match($this) {
            self::POSTED => __('enums.stock_status.posted'),
            self::DRAFT => __('enums.stock_status.draft'),
            self::VOIDED => __('enums.stock_status.voided'),
            self::CANCELLED => __('enums.stock_status.cancelled'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    public function color(): string
    {
        return match($this) {
            self::POSTED => 'emerald-600',
            self::DRAFT => 'gray-500',
            self::VOIDED => 'red-500',
            self::CANCELLED => 'yellow-500',
        };
    }
    public function label(): string
    {
        return $this->getLabel();
    }
}

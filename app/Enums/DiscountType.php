<?php

namespace App\Enums;

enum DiscountType: string
{
    case PERCENTAGE = 'percentage';
    case CURRENCY = 'currency';

    public function getLabel(): string
    {
        return match ($this) {
            self::PERCENTAGE => __('enums.discount_type.percentage'),
            self::CURRENCY => __('enums.discount_type.currency'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

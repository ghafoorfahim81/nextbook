<?php

namespace App\Enums;

enum StockDirectionType: string
{
    case IN = 'in';
    case OUT = 'out';

    public function getLabel(): string
    {
        return match ($this) {
            self::IN => __('enums.stock_direction_type.in'),
            self::OUT => __('enums.stock_direction_type.out'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

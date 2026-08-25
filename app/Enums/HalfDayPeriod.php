<?php

namespace App\Enums;

enum HalfDayPeriod: string
{
    case FirstHalf = 'first_half';
    case SecondHalf = 'second_half';

    public function getLabel(): string
    {
        return match ($this) {
            self::FirstHalf => __('enums.half_day_period.first_half'),
            self::SecondHalf => __('enums.half_day_period.second_half'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

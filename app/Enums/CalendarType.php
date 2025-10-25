<?php

namespace App\Enums;

enum CalendarType: string
{
    case JALALI = 'jalali';
    case GREGORIAN = 'gregorian';

    public function getLabel(): string
    {
        return match ($this) {
            self::JALALI => __('enums.calendar_type.jalali'),
            self::GREGORIAN => __('enums.calendar_type.gregorian'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

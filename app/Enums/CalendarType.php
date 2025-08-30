<?php

namespace App\Enums;

enum CalendarType: string
{
    case SHAMSI = 'shamsi';
    case MILADI = 'miladi';

    public function getLabel(): string
    {
        return match($this) {
            self::SHAMSI => __('enums.calendar_type.shamsi'),
            self::MILADI => __('enums.calendar_type.miladi'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    } 
}

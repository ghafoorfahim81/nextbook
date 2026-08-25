<?php

namespace App\Enums;

enum HolidayType: string
{
    case Public = 'public';
    case Religious = 'religious';
    case Company = 'company';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => __('enums.holiday_type.public'),
            self::Religious => __('enums.holiday_type.religious'),
            self::Company => __('enums.holiday_type.company'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

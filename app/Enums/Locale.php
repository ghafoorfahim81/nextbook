<?php

namespace App\Enums;

enum Locale: string
{
    case FA = 'fa';
    case EN = 'en';
    case PS = 'ps';

    public function getLabel(): string
    {
        return match($this) {
            self::FA => __('enums.locale.fa'),
            self::EN => __('enums.locale.en'),
            self::PS => __('enums.locale.ps'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

}

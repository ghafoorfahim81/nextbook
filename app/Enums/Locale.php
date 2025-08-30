<?php

namespace App\Enums;

enum Locale: string
{
    case FA = 'fa';
    case EN = 'en';
    case PA = 'pa';

    public function getLabel(): string
    {
        return match($this) {
            self::FA => __('enums.locale.fa'),
            self::EN => __('enums.locale.en'),
            self::PA => __('enums.locale.pa'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
     
}

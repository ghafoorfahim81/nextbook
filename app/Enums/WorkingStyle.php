<?php

namespace App\Enums;

enum WorkingStyle: string
{
    case NORMAL = 'normal';
    case SECONDARY = 'secondary';

    public function getLabel(): string
    {
        return match($this) {
            self::NORMAL => __('enums.working_style.normal'),
            self::SECONDARY => __('enums.working_style.secondary'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Single => __('enums.marital_status.single'),
            self::Married => __('enums.marital_status.married'),
            self::Divorced => __('enums.marital_status.divorced'),
            self::Widowed => __('enums.marital_status.widowed'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

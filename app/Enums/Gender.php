<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Male => __('enums.gender.male'),
            self::Female => __('enums.gender.female'),
            self::Other => __('enums.gender.other'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

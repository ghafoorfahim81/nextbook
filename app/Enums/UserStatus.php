<?php

namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case BLOCKED = 'blocked';

    public function getLabel(): string
    {
        return match($this) {
            self::ACTIVE => __('enums.user_status.active'),
            self::INACTIVE => __('enums.user_status.inactive'),
            self::BLOCKED => __('enums.user_status.blocked'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

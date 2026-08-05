<?php

namespace App\Enums;

enum LotStatus: string
{
    case ACTIVE = 'active';
    case BLOCKED = 'blocked';
    case RECALLED = 'recalled';
    case EXPIRED = 'expired';

    public function getLabel(): string
    {
        return match($this) {
            self::ACTIVE => __('enums.lot_status.active'),
            self::BLOCKED => __('enums.lot_status.blocked'),
            self::RECALLED => __('enums.lot_status.recalled'),
            self::EXPIRED => __('enums.lot_status.expired'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

/**
 * How an attendance row or punch got here. Kept so a disputed day can be traced
 * back to whether a human typed it or a device reported it.
 */
enum AttendanceSource: string
{
    case Manual = 'manual';
    case Roster = 'roster';
    case Device = 'device';
    case SelfService = 'self_service';
    case Import = 'import';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => __('enums.attendance_source.manual'),
            self::Roster => __('enums.attendance_source.roster'),
            self::Device => __('enums.attendance_source.device'),
            self::SelfService => __('enums.attendance_source.self_service'),
            self::Import => __('enums.attendance_source.import'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

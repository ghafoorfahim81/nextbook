<?php

namespace App\Enums;

enum AttendanceDeviceType: string
{
    case ZkTeco = 'zkteco';
    case Fingerspot = 'fingerspot';
    case Csv = 'csv';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::ZkTeco => __('enums.attendance_device_type.zkteco'),
            self::Fingerspot => __('enums.attendance_device_type.fingerspot'),
            self::Csv => __('enums.attendance_device_type.csv'),
            self::Other => __('enums.attendance_device_type.other'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

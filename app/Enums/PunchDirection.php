<?php

namespace App\Enums;

/**
 * Most inexpensive fingerprint terminals report a timestamp with no direction,
 * which is why Unknown exists and why the pairing algorithm has to infer.
 */
enum PunchDirection: string
{
    case In = 'in';
    case Out = 'out';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::In => __('enums.punch_direction.in'),
            self::Out => __('enums.punch_direction.out'),
            self::Unknown => __('enums.punch_direction.unknown'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

enum PosSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

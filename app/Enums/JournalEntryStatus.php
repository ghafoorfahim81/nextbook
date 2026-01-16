<?php

namespace App\Enums;

enum JournalEntryStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Blocked = 'blocked';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

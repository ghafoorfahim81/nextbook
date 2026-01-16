<?php

namespace App\Enums;

enum JournalEntrySource: string
{
    case Manual = 'manual';
    case POS = 'pos';
    case Inventory = 'inventory';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Adjustment = 'adjustment';
    case Opening = 'opening';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

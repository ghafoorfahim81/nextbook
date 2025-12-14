<?php

namespace App\Enums;

enum TransactionType: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

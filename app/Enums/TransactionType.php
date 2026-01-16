<?php

namespace App\Enums;

enum TransactionType: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';

    public function getLabel(): string
    {
        return match ($this) {
            self::DEBIT => __('enums.transaction_type.debit'),
            self::CREDIT => __('enums.transaction_type.credit'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

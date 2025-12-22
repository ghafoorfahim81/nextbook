<?php

namespace App\Enums;

enum LedgerType: string
{
    case CUSTOMER = 'customer';
    case SUPPLIER = 'supplier'; 

    public function getLabel(): string
    {
        return match ($this) {
            self::CUSTOMER => __('enums.ledger_type.customer'),
            self::SUPPLIER => __('enums.ledger_type.supplier'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

enum CreditTerms: string
{
    // Block a sale/purchase completely once the credit limit is exceeded.
    case STRICT = 'strict';
    // Warn the user but still allow the transaction to proceed.
    case WARNING = 'warning';
    // No restriction; the limit is tracked for information only.
    case FLEXIBLE = 'flexible';

    public function getLabel(): string
    {
        return match ($this) {
            self::STRICT => 'Strict',
            self::WARNING => 'Warning',
            self::FLEXIBLE => 'Flexible',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

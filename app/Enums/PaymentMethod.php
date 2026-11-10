<?php

namespace App\Enums;

/**
 * How money changed hands on a payment — the list offered in the payment
 * details dialog of a credit sale or purchase.
 *
 * Not to be confused with PaymentMode, which says how a payment is matched
 * against bills (bill by bill / on account).
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Check = 'check';
    case Card = 'card';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => __('enums.payment_method.cash'),
            self::BankTransfer => __('enums.payment_method.bank_transfer'),
            self::Check => __('enums.payment_method.check'),
            self::Card => __('enums.payment_method.card'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Label for a stored value; an unknown or legacy value is shown as-is. */
    public static function labelFor(self|string|null $value): string
    {
        if ($value instanceof self) {
            return $value->getLabel();
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return self::tryFrom($value)?->getLabel() ?? $value;
    }
}

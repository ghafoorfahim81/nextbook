<?php

namespace App\Enums;

enum PaymentMode: string
{
    case BillByBill = 'bill_by_bill';
    case OnAccount = 'on_account';

    public function getLabel(): string
    {
        return match ($this) {
            self::BillByBill => __('enums.payment_mode.bill_by_bill'),
            self::OnAccount => __('enums.payment_mode.on_account'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Label for a value that may already be an enum.
     *
     * The models cast `payment_mode` to this enum, so callers reading the
     * attribute get an instance — and `(string) $instance` is a fatal error,
     * not a value. Anything that has not been cast (a raw query, a legacy row)
     * still arrives as a string, so both have to be handled in one place rather
     * than repeated at every call site.
     */
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

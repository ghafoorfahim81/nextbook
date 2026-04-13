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
}

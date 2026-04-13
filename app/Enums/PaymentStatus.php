<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Paid => __('enums.payment_status.paid'),
            self::Unpaid => __('enums.payment_status.unpaid'),
            self::PartiallyPaid => __('enums.payment_status.partially_paid'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

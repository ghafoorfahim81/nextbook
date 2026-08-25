<?php

namespace App\Enums;

enum PayrollLinePaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => __('enums.payroll_line_payment_status.unpaid'),
            self::Partial => __('enums.payroll_line_payment_status.partial'),
            self::Paid => __('enums.payroll_line_payment_status.paid'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

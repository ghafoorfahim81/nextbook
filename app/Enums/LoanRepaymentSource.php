<?php

namespace App\Enums;

enum LoanRepaymentSource: string
{
    case Payroll = 'payroll';
    case Cash = 'cash';
    case Adjustment = 'adjustment';
    case WriteOff = 'write_off';

    public function getLabel(): string
    {
        return match ($this) {
            self::Payroll => __('enums.loan_repayment_source.payroll'),
            self::Cash => __('enums.loan_repayment_source.cash'),
            self::Adjustment => __('enums.loan_repayment_source.adjustment'),
            self::WriteOff => __('enums.loan_repayment_source.write_off'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

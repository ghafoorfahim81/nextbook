<?php

namespace App\Enums;

enum PayFrequency: string
{
    case Monthly = 'monthly';
    case Biweekly = 'biweekly';
    case Weekly = 'weekly';
    case Daily = 'daily';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => __('enums.pay_frequency.monthly'),
            self::Biweekly => __('enums.pay_frequency.biweekly'),
            self::Weekly => __('enums.pay_frequency.weekly'),
            self::Daily => __('enums.pay_frequency.daily'),
        };
    }

    /**
     * How many of these periods make up a month.
     *
     * Used to annualise a non-monthly wage before running it through the
     * monthly tax table. Without it a weekly-paid worker would sit inside the
     * zero-rate band four times over and pay no tax at all.
     */
    public function periodsPerMonth(): float
    {
        return match ($this) {
            self::Monthly => 1.0,
            // 26 fortnights and 52 weeks a year, over 12 months.
            self::Biweekly => 26 / 12,
            self::Weekly => 52 / 12,
            // Working days, not calendar days — a daily-wage worker is not paid
            // for the rest day.
            self::Daily => 26.0,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

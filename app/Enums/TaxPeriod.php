<?php

namespace App\Enums;

enum TaxPeriod: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => __('enums.tax_period.monthly'),
            self::Annual => __('enums.tax_period.annual'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

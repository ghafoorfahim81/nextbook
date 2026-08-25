<?php

namespace App\Enums;

enum LandedCostAllocationMethod: string
{
    case ByValue = 'by_value';
    case ByQuantity = 'by_quantity';
    case ByWeight = 'by_weight';
    case ByVolume = 'by_volume';

    public function getLabel(): string
    {
        return match ($this) {
            self::ByValue => __('enums.landed_cost_allocation_method.by_value'),
            self::ByQuantity => __('enums.landed_cost_allocation_method.by_quantity'),
            self::ByWeight => __('enums.landed_cost_allocation_method.by_weight'),
            self::ByVolume => __('enums.landed_cost_allocation_method.by_volume'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

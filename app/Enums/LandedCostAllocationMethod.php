<?php

namespace App\Enums;

enum LandedCostAllocationMethod: string
{
    case ByValue = 'by_value';
    case ByQuantity = 'by_quantity';
    case ByWeight = 'by_weight';
    case ByVolume = 'by_volume';
    case Equal = 'equal';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::ByValue => __('enums.landed_cost_allocation_method.by_value'),
            self::ByQuantity => __('enums.landed_cost_allocation_method.by_quantity'),
            self::ByWeight => __('enums.landed_cost_allocation_method.by_weight'),
            self::ByVolume => __('enums.landed_cost_allocation_method.by_volume'),
            self::Equal => __('enums.landed_cost_allocation_method.equal'),
            self::Manual => __('enums.landed_cost_allocation_method.manual'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

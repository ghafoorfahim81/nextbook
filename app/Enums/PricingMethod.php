<?php

namespace App\Enums;

/**
 * How an item's selling price is arrived at.
 *
 * FIXED is the stored price on the variant/lot. The measured methods compute
 * the price at sale time from a measurement on the piece multiplied by a rate,
 * and are inert until the market-rate module lands.
 */
enum PricingMethod: string
{
    case FIXED = 'fixed';
    case WEIGHT_BASED = 'weight_based';
    case AREA_BASED = 'area_based';
    case LENGTH_BASED = 'length_based';

    public function getLabel(): string
    {
        return match($this) {
            self::FIXED => __('enums.pricing_method.fixed'),
            self::WEIGHT_BASED => __('enums.pricing_method.weight_based'),
            self::AREA_BASED => __('enums.pricing_method.area_based'),
            self::LENGTH_BASED => __('enums.pricing_method.length_based'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

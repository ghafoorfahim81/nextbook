<?php

namespace App\Enums;

enum ComponentCalculationType: string
{
    case Fixed = 'fixed';
    case PercentOfBasic = 'percent_of_basic';
    case PercentOfGross = 'percent_of_gross';
    case PerDay = 'per_day';
    case PerHour = 'per_hour';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => __('enums.component_calculation_type.fixed'),
            self::PercentOfBasic => __('enums.component_calculation_type.percent_of_basic'),
            self::PercentOfGross => __('enums.component_calculation_type.percent_of_gross'),
            self::PerDay => __('enums.component_calculation_type.per_day'),
            self::PerHour => __('enums.component_calculation_type.per_hour'),
        };
    }

    public function usesPercentage(): bool
    {
        return $this === self::PercentOfBasic || $this === self::PercentOfGross;
    }

    /**
     * Percent-of-gross has to be resolved AFTER every other earning, or the
     * gross it references is incomplete. Ordering is a property of the
     * calculation type, not of the component's own sequence number.
     */
    public function resolutionPass(): int
    {
        return $this === self::PercentOfGross ? 2 : 1;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

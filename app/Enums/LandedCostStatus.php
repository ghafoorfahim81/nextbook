<?php

namespace App\Enums;

enum LandedCostStatus: string
{
    case Draft = 'draft';
    case Allocated = 'allocated';
    case Posted = 'posted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('enums.landed_cost_status.draft'),
            self::Allocated => __('enums.landed_cost_status.allocated'),
            self::Posted => __('enums.landed_cost_status.posted'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

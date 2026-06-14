<?php

namespace App\Enums;

enum CostingMethod: string
{
    case FIFO = 'fifo';
    case LIFO = 'lifo';
    case WEIGHTED_AVERAGE = 'weighted_average';

    public function getLabel(): string
    {
        return match ($this) {
            self::FIFO => __('enums.costing_method.fifo'),
            self::LIFO => __('enums.costing_method.lifo'),
            self::WEIGHTED_AVERAGE => __('enums.costing_method.weighted_average'), 
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

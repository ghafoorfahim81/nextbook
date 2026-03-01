<?php

namespace App\Enums;

enum CostingMethod: string
{
    case FIFO = 'fifo';
    case LIFO = 'lifo';

    public function getLabel(): string
    {
        return match ($this) {
            self::FIFO => __('enums.costing_method.fifo'),
            self::LIFO => __('enums.costing_method.lifo'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

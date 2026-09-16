<?php

namespace App\Enums;

enum CostingMethod: string
{
    case FIFO = 'fifo';
    case LIFO = 'lifo';
    case WEIGHTED_AVERAGE = 'weighted_average';
    case SPECIFIC = 'specific';

    public function getLabel(): string
    {
        return match ($this) {
            self::FIFO => __('enums.costing_method.fifo'),
            self::LIFO => __('enums.costing_method.lifo'),
            self::WEIGHTED_AVERAGE => __('enums.costing_method.weighted_average'),
            self::SPECIFIC => __('enums.costing_method.specific'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The methods the costing engine actually implements.
     *
     * SPECIFIC is defined but nothing costs by it — StockService::handleOut
     * branches FIFO and LIFO and treats everything else as weighted average —
     * so offering it on a form hands the user weighted average under another
     * name. It stays in the enum so existing rows still resolve.
     */
    public static function selectable(): array
    {
        return [self::FIFO, self::LIFO, self::WEIGHTED_AVERAGE];
    }
}

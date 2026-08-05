<?php

namespace App\Enums;

enum ItemCondition: string
{
    case NEW = 'new';
    case REFURBISHED = 'refurbished';
    case USED = 'used';
    case DAMAGED = 'damaged';

    public function getLabel(): string
    {
        return match($this) {
            self::NEW => __('enums.item_condition.new'),
            self::REFURBISHED => __('enums.item_condition.refurbished'),
            self::USED => __('enums.item_condition.used'),
            self::DAMAGED => __('enums.item_condition.damaged'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

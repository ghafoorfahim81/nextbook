<?php

namespace App\Enums;

enum ItemType: string
{
    case INVENTORY_MATERIALS = 'inventory_materials';
    case NON_INVENTORY_MATERIALS = 'non_inventory_materials';
    case RAW_MATERIALS = 'raw_materials';
    case FINISHED_GOOD_ITEMS = 'finished_good_items';
    case INVENTORY_SERVICES = 'inventory_services';

    public function getLabel(): string
    {
        return match ($this) {
            self::INVENTORY_MATERIALS => __('enums.item_type.inventory_materials'),
            self::NON_INVENTORY_MATERIALS => __('enums.item_type.non_inventory_materials'),
            self::RAW_MATERIALS => __('enums.item_type.raw_materials'),
            self::FINISHED_GOOD_ITEMS => __('enums.item_type.finished_good_items'),
            self::INVENTORY_SERVICES => __('enums.item_type.inventory_services'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

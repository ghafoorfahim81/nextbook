<?php

namespace App\Enums;

enum StockSourceType: string
{
    case PURCHASE = 'purchase';
    case PURCHASE_RETURN = 'purchase_return';
    case SALE = 'sale';
    case SALE_RETURN = 'sale_return';
    case STOCK_ADJUSTMENT = 'stock_adjustment';
    case ITEM_TRANSFER = 'item_transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::PURCHASE => __('enums.stock_source_type.purchase'),
            self::PURCHASE_RETURN => __('enums.stock_source_type.purchase_return'),
            self::SALE => __('enums.stock_source_type.sale'),
            self::SALE_RETURN => __('enums.stock_source_type.sale_return'),
            self::STOCK_ADJUSTMENT => __('enums.stock_source_type.stock_adjustment'),
            self::ITEM_TRANSFER => __('enums.stock_source_type.item_transfer'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

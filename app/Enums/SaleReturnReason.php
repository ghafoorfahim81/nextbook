<?php

namespace App\Enums;

enum SaleReturnReason: string
{
    case DAMAGED = 'damaged';
    case WRONG_ITEM = 'wrong_item';
    case DEFECTIVE = 'defective';
    case EXPIRED = 'expired';
    case CUSTOMER_CHANGED_MIND = 'customer_changed_mind';
    case DUPLICATE_ORDER = 'duplicate_order';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return __('enums.sale_return_reason.' . $this->value);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn (self $reason) => [
            'id' => $reason->value,
            'name' => $reason->getLabel(),
        ], self::cases());
    }
}

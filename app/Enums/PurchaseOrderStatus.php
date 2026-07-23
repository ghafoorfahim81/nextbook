<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => __('enums.purchase_order_status.draft'),
            self::POSTED => __('enums.purchase_order_status.posted'),
            self::COMPLETED => __('enums.purchase_order_status.completed'),
            self::CANCELLED => __('enums.purchase_order_status.cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::POSTED => 'blue',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn (self $status) => [
            'id' => $status->value,
            'name' => $status->getLabel(),
        ], self::cases());
    }
}

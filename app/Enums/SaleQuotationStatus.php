<?php

namespace App\Enums;

enum SaleQuotationStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => __('enums.sale_quotation_status.draft'),
            self::POSTED => __('enums.sale_quotation_status.posted'),
            self::CANCELLED => __('enums.sale_quotation_status.cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::POSTED => 'blue',
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

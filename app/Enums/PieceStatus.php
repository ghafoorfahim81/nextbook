<?php

namespace App\Enums;

enum PieceStatus: string
{
    case IN_STOCK = 'in_stock';
    case RESERVED = 'reserved';
    case SOLD = 'sold';
    case RETURNED = 'returned';
    case SCRAPPED = 'scrapped';

    public function getLabel(): string
    {
        return match($this) {
            self::IN_STOCK => __('enums.piece_status.in_stock'),
            self::RESERVED => __('enums.piece_status.reserved'),
            self::SOLD => __('enums.piece_status.sold'),
            self::RETURNED => __('enums.piece_status.returned'),
            self::SCRAPPED => __('enums.piece_status.scrapped'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

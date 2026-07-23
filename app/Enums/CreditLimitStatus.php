<?php

namespace App\Enums;

enum CreditLimitStatus: string
{
    case BLOCK = 'Block';
    case INDICATE = 'Indicate';

    public function getLabel(): string
    {
        return $this->value;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

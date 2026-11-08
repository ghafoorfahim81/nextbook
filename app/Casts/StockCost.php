<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A per-unit stock cost, stored to 8 decimal places.
 *
 * Inventory value is read back as quantity x unit_cost and has to agree with the
 * inventory account on the balance sheet. At 4 places, 100 of freight over 7,000
 * pieces became 0.0143 a piece, or 100.10 of stock against 100.00 in the ledger.
 *
 * Reads keep at least 4 places, so an ordinary cost still reads "30.0000" and
 * only a figure that needs more digits shows them.
 */
class StockCost implements CastsAttributes
{
    public const SCALE = 8;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        [$whole, $fraction] = explode('.', self::format($value));

        return $whole.'.'.str_pad(rtrim($fraction, '0'), 4, '0');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null || $value === '' ? null : self::format($value);
    }

    private static function format(mixed $value): string
    {
        return number_format((float) $value, self::SCALE, '.', '');
    }
}

<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Next voucher number for models whose `number` column is a STRING.
 *
 * `MAX(number)` on a text column sorts lexicographically, not numerically:
 *
 *     '1' < '10' < '11' < '2' < '9'
 *
 * so with receipts 1..10 on file the maximum is '9', and '9' + 1 is 10 — the
 * number that already exists. The counter sticks there permanently, handing out
 * 10 to every new receipt. Casting inside the aggregate is the fix; sorting the
 * rows in PHP afterwards would work too but pulls the whole table back.
 *
 * Rows whose number is not purely numeric are ignored rather than crashing the
 * cast — a hand-entered "INV-2024/3" should not stop the sequence.
 *
 * Trashed rows are counted. A soft-deleted receipt still occupies its number
 * and can be restored, and issuing that number again would put two receipts
 * with the same number in front of the user.
 *
 * Postgres-specific (`~`, `::bigint`), which this project already is.
 */
trait HasSequentialNumber
{
    public static function nextNumber(): int
    {
        $query = static::query();

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withTrashed();
        }

        $highest = $query
            ->selectRaw("MAX(CASE WHEN number ~ '^[0-9]+$' THEN number::bigint END) as highest_number")
            ->value('highest_number');

        return ((int) $highest) + 1;
    }
}

<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Next document number, one series per company and document type.
 *
 * Written for the STRING `number` columns first, where the trap below lives,
 * and used for the integer ones too.
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
 * The series is the company's, not the branch's: one sequence per company and
 * document type, so Kabul takes 001 and Herat 002. The branch scope is lifted
 * for the count, the company scope stays.
 *
 * Postgres-specific (`~`, `::bigint`), which this project already is.
 */
trait HasSequentialNumber
{
    public static function nextNumber(): int
    {
        static::lockNumberSeries();

        $query = static::query()->withoutGlobalScope('branchSpecific');

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withTrashed();
        }

        $highest = $query
            // Through text, so the same expression serves the integer number
            // columns of sales and purchases and the string ones of vouchers.
            ->selectRaw("MAX(CASE WHEN number::text ~ '^[0-9]+$' THEN number::text::bigint END) as highest_number")
            ->value('highest_number');

        return ((int) $highest) + 1;
    }

    /**
     * Hold this series against other transactions until ours ends.
     *
     * "Highest + 1" read and written by two transactions at once hands both the
     * same number. The unique index on `(company_id, number)` stops the
     * duplicate reaching the table, but it stops it by failing the second save:
     * a cashier gets an error on a perfectly good sale, at the busiest moment,
     * which is when two tills are most likely to post together.
     *
     * An advisory lock turns that collision into a short wait. The second
     * transaction blocks here, and by the time it reads MAX the first has
     * committed — Postgres runs READ COMMITTED, so each statement takes a fresh
     * snapshot and the just-committed row is visible. It leaves with the next
     * number instead of an exception.
     *
     * `pg_advisory_xact_lock` and not the session-scoped form: the transaction
     * ending releases it, including when it rolls back. Nothing has to remember
     * to unlock, and a failed save cannot leave the series wedged.
     *
     * The key is the series — this table, in this company — so Kabul posting a
     * sale never waits on Herat posting a purchase. `hashtext` collisions would
     * put two unrelated series on one lock, which costs a little needless
     * waiting and is never wrong.
     *
     * Two things this deliberately does not do:
     *
     *  - It does nothing outside a transaction. An `xact` lock taken there is
     *    released as the statement ends, so it would protect nothing; the
     *    callers that matter — the `store` paths — all run inside
     *    `DB::transaction`. The ones that do not are the `create` screens
     *    asking what the next number will look like, and locking there would
     *    make a page render queue behind whatever posting is in flight.
     *
     *    No test covers that branch: the suite runs every test inside a
     *    transaction of its own, so `transactionLevel()` is never 0 and the
     *    skip cannot be reached. What is covered is the case that matters —
     *    see VoucherNumberingTest::test_allocating_a_number_locks_the_series.
     *  - It does not fall back to a global lock when no company is resolved.
     *    With no company the key is the table alone, which over-serializes a
     *    console command or a job across companies. That is slower and still
     *    correct, and those paths are not contended.
     */
    protected static function lockNumberSeries(): void
    {
        $connection = static::query()->getConnection();

        if ($connection->getDriverName() !== 'pgsql' || $connection->transactionLevel() === 0) {
            return;
        }

        $series = (new static)->getTable().'|'.(static::activeCompanyId() ?? '');

        $connection->statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$series]);
    }
}

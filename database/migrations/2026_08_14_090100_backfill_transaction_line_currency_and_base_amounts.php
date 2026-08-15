<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Step 2 of line-level multi-currency: backfill.
 *
 * Every existing line inherits its transaction's currency and rate. That is
 * correct by construction — the old schema could not express anything else.
 *
 * Runs per branch (single shared database; branch is the tenancy boundary here).
 * Soft-deleted rows are included on purpose: a trashed line that gets restored
 * must still carry consistent base amounts.
 */
return new class extends Migration
{
    /** Rows per statement once a branch crosses the chunk threshold. */
    private const CHUNK_SIZE = 50_000;

    /** Below this, a single UPDATE is cheaper than looping. */
    private const CHUNK_THRESHOLD = 200_000;

    public function up(): void
    {
        $branches = DB::table('transactions')
            ->select('branch_id')
            ->distinct()
            ->pluck('branch_id');

        foreach ($branches as $branchId) {
            DB::transaction(function () use ($branchId) {
                $lines = $this->backfillLines($branchId);
                $headers = $this->backfillBaseCurrency($branchId);

                Log::info('[multi-currency backfill] branch processed', [
                    'branch_id' => $branchId,
                    'lines_updated' => $lines,
                    'transactions_stamped_with_base_currency' => $headers,
                ]);
            });
        }
    }

    /**
     * Copy currency + rate down from the header and value each line in base.
     */
    private function backfillLines(string $branchId): int
    {
        $pending = DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('t.branch_id', $branchId)
            ->whereNull('tl.currency_id')
            ->count();

        if ($pending === 0) {
            return 0;
        }

        if ($pending <= self::CHUNK_THRESHOLD) {
            return $this->updateLineBatch($branchId, null);
        }

        $total = 0;

        do {
            $updated = $this->updateLineBatch($branchId, self::CHUNK_SIZE);
            $total += $updated;

            if ($updated > 0) {
                Log::info('[multi-currency backfill] chunk committed', [
                    'branch_id' => $branchId,
                    'rows' => $updated,
                    'running_total' => $total,
                ]);
            }
        } while ($updated > 0);

        return $total;
    }

    private function updateLineBatch(string $branchId, ?int $limit): int
    {
        $limitSql = $limit === null ? '' : 'LIMIT ' . (int) $limit;

        return DB::affectingStatement(
            <<<SQL
            WITH batch AS (
                SELECT tl.id,
                       t.currency_id AS source_currency_id,
                       t.rate        AS source_rate,
                       tl.debit      AS source_debit,
                       tl.credit     AS source_credit
                FROM transaction_lines tl
                JOIN transactions t ON t.id = tl.transaction_id
                WHERE t.branch_id = ?
                  AND tl.currency_id IS NULL
                {$limitSql}
            )
            UPDATE transaction_lines tl
            SET currency_id = b.source_currency_id,
                rate        = b.source_rate,
                base_debit  = ROUND(b.source_debit  * b.source_rate, 4),
                base_credit = ROUND(b.source_credit * b.source_rate, 4)
            FROM batch b
            WHERE tl.id = b.id
            SQL,
            [$branchId]
        );
    }

    /**
     * Stamp the header with the branch's functional currency (AFN).
     */
    private function backfillBaseCurrency(string $branchId): int
    {
        // Currencies are per-branch (unique on branch_id + code), so each branch
        // has its own AFN row with its own ULID. Resolving a single global AFN
        // would point every branch at another branch's currency record.
        $baseCurrencyId = DB::table('currencies')
            ->where('branch_id', $branchId)
            ->where('is_base_currency', true)
            ->whereNull('deleted_at')
            ->value('id')
            ?? DB::table('currencies')
                ->where('branch_id', $branchId)
                ->where('code', 'AFN')
                ->whereNull('deleted_at')
                ->value('id');

        if ($baseCurrencyId === null) {
            Log::warning('[multi-currency backfill] branch has no base currency; headers left null', [
                'branch_id' => $branchId,
            ]);

            return 0;
        }

        return DB::table('transactions')
            ->where('branch_id', $branchId)
            ->whereNull('base_currency_id')
            ->update(['base_currency_id' => $baseCurrencyId]);
    }

    public function down(): void
    {
        DB::table('transaction_lines')->update([
            'currency_id' => null,
            'rate' => null,
            'base_debit' => null,
            'base_credit' => null,
        ]);

        DB::table('transactions')->update(['base_currency_id' => null]);
    }
};

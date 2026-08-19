<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Step 3 of line-level multi-currency: verify + repair rounding.
 *
 * Rounding each line independently can break the base-currency balance even
 * when the document currency balances perfectly — three lines at 33.333 USD
 * each round to a base total that misses the credit side by a cent.
 *
 * The repair absorbs the remainder into the largest line of the transaction.
 * It never touches `rate`: the rate is a historical fact, the rounding
 * remainder is not.
 */
class VerifyTransactionBaseAmounts extends Command
{
    protected $signature = 'transactions:verify-base
                            {--branch= : Restrict to a single branch id — omit to check every branch}
                            {--fix : Absorb the difference into the largest line of each unbalanced transaction}';

    protected $description = 'Verify that every transaction balances in base currency, and optionally repair rounding drift';

    public function handle(): int
    {
        $unbalanced = $this->findUnbalanced();

        if ($unbalanced->isEmpty()) {
            $this->components->info('All transactions balance in base currency.');

            return self::SUCCESS;
        }

        $this->report($unbalanced);

        if (! $this->option('fix')) {
            $this->components->error(sprintf(
                '%d transaction(s) do not balance in base currency. Re-run with --fix to repair.',
                $unbalanced->count()
            ));

            return self::FAILURE;
        }

        return $this->repair($unbalanced);
    }

    /**
     * Transactions whose live lines disagree between base debit and base credit.
     */
    private function findUnbalanced()
    {
        return DB::table('transactions as t')
            ->join('transaction_lines as tl', 'tl.transaction_id', '=', 't.id')
            ->whereNull('tl.deleted_at')
            ->whereNull('t.deleted_at')
            ->when(
                $this->option('branch'),
                fn ($query, $branchId) => $query->where('t.branch_id', $branchId)
            )
            ->groupBy('t.id', 't.voucher_number', 't.date', 't.branch_id')
            ->havingRaw('SUM(tl.base_debit) <> SUM(tl.base_credit)')
            ->orderBy('t.date')
            ->select(
                't.id',
                't.voucher_number',
                't.date',
                't.branch_id',
                DB::raw('SUM(tl.base_debit) as dr'),
                DB::raw('SUM(tl.base_credit) as cr')
            )
            ->get();
    }

    private function report($unbalanced): void
    {
        $this->components->warn(sprintf('%d unbalanced transaction(s):', $unbalanced->count()));

        $this->table(
            ['Voucher', 'Date', 'Base Dr', 'Base Cr', 'Difference'],
            $unbalanced->map(fn ($row) => [
                $row->voucher_number ?? $row->id,
                $row->date,
                number_format((float) $row->dr, 4),
                number_format((float) $row->cr, 4),
                number_format((float) $row->dr - (float) $row->cr, 4),
            ])->all()
        );
    }

    private function repair($unbalanced): int
    {
        $repaired = 0;
        $skipped = [];

        foreach ($unbalanced as $row) {
            $difference = bcsub((string) $row->dr, (string) $row->cr, 4);

            $largest = DB::table('transaction_lines')
                ->where('transaction_id', $row->id)
                ->whereNull('deleted_at')
                ->orderByRaw('GREATEST(base_debit, base_credit) DESC')
                ->select('id', 'base_debit', 'base_credit')
                ->first();

            if ($largest === null) {
                $skipped[] = [$row->voucher_number ?? $row->id, 'no live lines'];

                continue;
            }

            // Absorbing on a debit line means removing the excess debit;
            // on a credit line it means adding the matching credit. Either
            // way the untouched side stays at zero, so chk_single_side holds.
            $onDebitSide = bccomp((string) $largest->base_debit, (string) $largest->base_credit, 4) >= 0;

            $column = $onDebitSide ? 'base_debit' : 'base_credit';
            $current = $onDebitSide ? $largest->base_debit : $largest->base_credit;
            $adjusted = $onDebitSide
                ? bcsub((string) $current, $difference, 4)
                : bcadd((string) $current, $difference, 4);

            if (bccomp($adjusted, '0', 4) < 0) {
                $skipped[] = [
                    $row->voucher_number ?? $row->id,
                    'repair would drive the largest line negative',
                ];

                continue;
            }

            DB::table('transaction_lines')
                ->where('id', $largest->id)
                ->update([$column => $adjusted, 'updated_at' => now()]);

            $repaired++;
        }

        if ($skipped !== []) {
            $this->components->error(sprintf('%d transaction(s) could not be repaired:', count($skipped)));
            $this->table(['Voucher', 'Reason'], $skipped);
        }

        $this->components->info(sprintf('Repaired %d transaction(s). Re-run to confirm clean.', $repaired));

        return $skipped === [] ? self::SUCCESS : self::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Accounting\FiscalYear;
use App\Models\Administration\Branch;
use App\Services\Accounting\FiscalYearService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generate the financial years an existing install has been trading through.
 *
 * Without this, a database with three years of history has one financial year
 * — the one provisioning created — and every older voucher sits in a period
 * that does not exist. Nothing breaks (the guard treats an ungenerated date as
 * open) but nobody can close a past year either, which is the whole point.
 *
 * Years are created OPEN. Deciding which of them to close is a judgement about
 * whether their books are finished, and that belongs to the accountant, not to
 * a backfill.
 */
class BackfillFiscalYears extends Command
{
    protected $signature = 'accounting:backfill-fiscal-years
                            {--branch= : Restrict to one branch id}
                            {--dry-run : Report what would be created without writing}';

    protected $description = 'Generate financial years and periods covering existing transaction history';

    public function handle(FiscalYearService $fiscalYears): int
    {
        $branches = Branch::query()
            ->withoutGlobalScopes()
            ->when($this->option('branch'), fn ($query, $id) => $query->where('id', $id))
            ->get(['id', 'name']);

        if ($branches->isEmpty()) {
            $this->warn('No branches found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;

        foreach ($branches as $branch) {
            $earliest = DB::table('transactions')
                ->where('branch_id', $branch->id)
                ->whereNull('deleted_at')
                ->min('date');

            if (! $earliest) {
                $this->line("  {$branch->name}: no transactions, skipping");

                continue;
            }

            // Walk forward from the oldest voucher to today. Today rather than
            // the newest voucher, so a branch that has not traded this month
            // still gets a year it can post into.
            $cursor = Carbon::parse($earliest)->startOfDay();
            $limit = Carbon::today();

            while ($cursor->lessThanOrEqualTo($limit)) {
                $existing = $fiscalYears->yearFor($cursor->toDateString(), $branch->id);

                if ($existing) {
                    // Jump to the day after the year we just found, so an
                    // already-covered span costs one query rather than 365.
                    $cursor = $existing->end_date->copy()->addDay()->startOfDay();

                    continue;
                }

                [$start, $end] = $fiscalYears->boundsContaining($cursor->toDateString());

                if ($dryRun) {
                    $this->line("  {$branch->name}: would create {$start->toDateString()} to {$end->toDateString()}");
                } else {
                    $year = $fiscalYears->generate($cursor->toDateString(), $branch->id);
                    $this->line("  {$branch->name}: created {$year->name} ({$year->start_date->toDateString()} to {$year->end_date->toDateString()})");
                }

                $created++;
                $cursor = $end->copy()->addDay()->startOfDay();
            }
        }

        $verb = $dryRun ? 'would be created' : 'created';
        $this->info("{$created} financial year(s) {$verb}.");

        if (! $dryRun && $created > 0) {
            $this->comment(
                'All new years are OPEN. Close the ones whose books are finished from '
                . 'Accounting → Financial Years.'
            );
        }

        return self::SUCCESS;
    }
}

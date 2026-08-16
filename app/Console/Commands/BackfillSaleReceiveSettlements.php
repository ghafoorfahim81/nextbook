<?php

namespace App\Console\Commands;

use App\Services\Accounting\SaleReceiveBackfill;
use Illuminate\Console\Command;

/**
 * Moves the legacy sale_receives allocations into settlements.
 *
 * Run with --dry-run first. Anything it cannot match with certainty is listed
 * rather than guessed at, and those rows want a human decision before the old
 * table is dropped.
 */
class BackfillSaleReceiveSettlements extends Command
{
    protected $signature = 'settlements:backfill-sale-receives {--dry-run : Report what would happen without writing}';

    protected $description = 'Migrate sale_receives allocations into the settlements table';

    public function handle(SaleReceiveBackfill $backfill): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $result = $backfill->run($dryRun);

        $this->info(($dryRun ? 'Would migrate ' : 'Migrated ') . $result['migrated'] . ' allocation(s).');

        if ($result['already_present'] > 0) {
            $this->line($result['already_present'] . ' already migrated, left alone.');
        }

        if ($result['skipped'] === []) {
            return self::SUCCESS;
        }

        $this->warn(count($result['skipped']) . ' row(s) skipped — these need a decision before sale_receives is dropped:');

        $this->table(
            ['sale_receive_id', 'sale_id', 'receipt_id', 'amount', 'reason'],
            collect($result['skipped'])->map(fn (array $row) => [
                $row['sale_receive_id'],
                $row['sale_id'],
                $row['receipt_id'],
                $row['amount'],
                $row['reason'],
            ])->all()
        );

        return self::SUCCESS;
    }
}

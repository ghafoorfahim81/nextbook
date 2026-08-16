<?php

use App\Services\Accounting\SaleReceiveBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Carries the old allocations across before the next migration drops them.
 *
 * Deliberately a separate file from the drop, so `settlements:backfill-sale-receives
 * --dry-run` can be inspected and any skipped row dealt with while the source
 * data is still there to look at.
 */
return new class extends Migration
{
    public function up(): void
    {
        $result = app(SaleReceiveBackfill::class)->run();

        Log::info('sale_receives backfill complete.', [
            'migrated' => $result['migrated'],
            'already_present' => $result['already_present'],
            'skipped' => count($result['skipped']),
        ]);
    }

    public function down(): void
    {
        // Settlements written by the backfill are indistinguishable from ones
        // written by a real receipt once they exist, and deleting by date would
        // take live data with them. Rolling this back is a restore, not a
        // migration.
    }
};

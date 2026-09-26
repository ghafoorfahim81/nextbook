<?php

namespace Tests\Feature\Inventory;

use App\Enums\TransactionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The migration that puts item transfers on the shared status vocabulary.
 *
 * A fresh database is built with the new values already, so the rename only
 * ever runs against a database that still holds the old ones — exactly the
 * case a test suite never sees unless it recreates it on purpose.
 */
class ItemTransferStatusMigrationTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private const LEGACY = ['pending', 'completed', 'cancelled'];

    public function test_it_renames_the_statuses_a_legacy_database_still_holds(): void
    {
        $ctx = $this->bootstrapErpContext();

        $this->putTheColumnBackTheWayItWas();

        foreach (self::LEGACY as $status) {
            DB::table('item_transfers')->insert([
                'id' => (string) Str::ulid(),
                'date' => '2026-01-01',
                'from_warehouse_id' => $ctx['warehouse']->id,
                'to_warehouse_id' => $ctx['warehouse']->id,
                'status' => $status,
                'branch_id' => $ctx['branch']->id,
                'created_by' => $ctx['user']->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(
            ['cancelled' => 1, 'completed' => 1, 'pending' => 1],
            $this->statusCounts(),
        );

        $this->runTheMigration();

        $this->assertSame(
            ['draft' => 1, 'posted' => 1, 'reversed' => 1],
            $this->statusCounts(),
            'Every legacy status must land on its counterpart in the shared vocabulary.',
        );
    }

    public function test_it_leaves_an_already_migrated_database_alone(): void
    {
        $ctx = $this->bootstrapErpContext();

        DB::table('item_transfers')->insert([
            'id' => (string) Str::ulid(),
            'date' => '2026-01-01',
            'from_warehouse_id' => $ctx['warehouse']->id,
            'to_warehouse_id' => $ctx['warehouse']->id,
            'status' => TransactionStatus::POSTED->value,
            'branch_id' => $ctx['branch']->id,
            'created_by' => $ctx['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Running it a second time is what a redeploy does.
        $this->runTheMigration();
        $this->runTheMigration();

        $this->assertSame(['posted' => 1], $this->statusCounts());
    }

    public function test_the_column_accepts_the_new_values_afterwards(): void
    {
        $ctx = $this->bootstrapErpContext();
        $this->putTheColumnBackTheWayItWas();
        $this->runTheMigration();

        foreach ([TransactionStatus::DRAFT, TransactionStatus::POSTED, TransactionStatus::REVERSED] as $status) {
            DB::table('item_transfers')->insert([
                'id' => (string) Str::ulid(),
                'date' => '2026-01-01',
                'from_warehouse_id' => $ctx['warehouse']->id,
                'to_warehouse_id' => $ctx['warehouse']->id,
                'status' => $status->value,
                'branch_id' => $ctx['branch']->id,
                'created_by' => $ctx['user']->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(3, DB::table('item_transfers')->count());
    }

    /** @return array<string, int> */
    private function statusCounts(): array
    {
        $counts = DB::table('item_transfers')
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();

        ksort($counts);

        return $counts;
    }

    private function putTheColumnBackTheWayItWas(): void
    {
        $list = implode(', ', array_map(fn (string $s) => "'" . $s . "'", self::LEGACY));

        DB::statement('ALTER TABLE item_transfers DROP CONSTRAINT IF EXISTS item_transfers_status_check');
        DB::statement("ALTER TABLE item_transfers ALTER COLUMN status SET DEFAULT 'pending'");
        DB::statement("ALTER TABLE item_transfers ADD CONSTRAINT item_transfers_status_check CHECK (status::text = ANY (ARRAY[{$list}]::text[]))");
    }

    private function runTheMigration(): void
    {
        $migration = require database_path('migrations/2026_09_26_000001_align_item_transfer_status_with_documents.php');
        $migration->up();
    }
}

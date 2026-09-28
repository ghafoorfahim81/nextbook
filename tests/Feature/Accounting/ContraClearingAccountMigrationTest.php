<?php

namespace Tests\Feature\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The migration that provisions Set-off Clearing on branches created before
 * the account existed.
 *
 * Under RefreshDatabase the migration runs against an empty database and
 * returns early — there are no users to attribute the row to — so its real
 * path is never exercised by an ordinary test run. This runs it the way it
 * will run on a live database: branches present, users present, and an
 * account number that may already be taken.
 */
class ContraClearingAccountMigrationTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_26_000002_create_contra_clearing_account.php');
    }

    private function deleteTheClearingAccount(string $branchId): void
    {
        DB::table('accounts')
            ->where('branch_id', $branchId)
            ->where('slug', 'contra-clearing')
            ->delete();
    }

    public function test_it_provisions_the_account_on_an_existing_branch(): void
    {
        $ctx = $this->bootstrapErpContext();
        $this->deleteTheClearingAccount($ctx['branch']->id);

        $this->migration()->up();

        $account = DB::table('accounts')
            ->where('branch_id', $ctx['branch']->id)
            ->where('slug', 'contra-clearing')
            ->first();

        $this->assertNotNull($account, 'The migration did not create the clearing account.');
        $this->assertSame('3070', $account->number);

        $type = DB::table('account_types')->where('id', $account->account_type_id)->first();
        $this->assertSame('other-current-asset', $type->slug);
    }

    public function test_running_it_twice_does_not_create_a_second_one(): void
    {
        $ctx = $this->bootstrapErpContext();
        $this->deleteTheClearingAccount($ctx['branch']->id);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(
            1,
            DB::table('accounts')
                ->where('branch_id', $ctx['branch']->id)
                ->where('slug', 'contra-clearing')
                ->count(),
        );
    }

    public function test_it_walks_past_a_number_someone_else_already_used(): void
    {
        $ctx = $this->bootstrapErpContext();
        $this->deleteTheClearingAccount($ctx['branch']->id);

        // Account numbers are unique per branch and users add their own, so
        // 3070 may well be occupied by the time this runs.
        DB::table('accounts')
            ->where('branch_id', $ctx['branch']->id)
            ->where('slug', 'inventory-stock')
            ->update(['number' => '3070']);

        $this->migration()->up();

        $account = DB::table('accounts')
            ->where('branch_id', $ctx['branch']->id)
            ->where('slug', 'contra-clearing')
            ->first();

        $this->assertNotNull($account);
        $this->assertNotSame('3070', $account->number, 'The migration reused a number already in use.');
    }
}

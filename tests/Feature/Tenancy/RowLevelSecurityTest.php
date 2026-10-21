<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account\Account;
use App\Models\Administration\Company;
use App\Support\CompanyDatabaseContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Step 10: proves Postgres refuses to hand one company another company's rows,
 * independently of whether the application remembered to ask it not to.
 *
 * ## Why these tests switch role instead of opening a second connection
 *
 * The suite runs under RefreshDatabase, so every row a test creates lives in an
 * uncommitted transaction. A genuinely separate connection logging in as
 * `nextbook_app` would see none of it — not because the policies worked, but
 * because the data was not there yet, and the tests would pass against an empty
 * table and prove nothing.
 *
 * `SET ROLE` avoids that: it changes the role Postgres evaluates policies
 * against while staying on the same session and inside the same transaction, so
 * the test's own fixtures are visible and the policies are the only thing that
 * can hide them. It is also what the real deployment does, one step removed —
 * production logs in as `nextbook_app` directly, which lands in the same place.
 *
 * ## What these tests do not cover
 *
 * The other 900-odd tests still run as the schema owner, which is exempt from
 * the policies. So nothing here proves that every application code path sets
 * `app.company_id` before it reads a protected table. A job or console command
 * that forgets will return empty results in production while its test stays
 * green. That gap is listed in docs/phase-2-next-session-fa.md; closing it
 * means running the whole suite as the app role, which is its own piece of work.
 */
class RowLevelSecurityTest extends TestCase
{
    use BuildsErpContext;

    /**
     * The only tables that carry a company and are allowed to have no policy.
     *
     * Stated here as its own list rather than imported from the migration: if
     * the two drift, that is the thing worth failing on. Adding a table here is
     * a decision to leave it unprotected, and should need a reason written in
     * the migration beside it.
     */
    private const UNPROTECTED_TABLES = [
        'users',
        'branches',
    ];

    /**
     * Tables whose shared, company-less reference rows everyone may read.
     */
    private const SHARED_REFERENCE_TABLES = [
        'customer_groups',
        'payment_terms',
    ];

    /**
     * Tables that accept an event they cannot attribute to a company.
     */
    private const AUDIT_TRAIL_TABLES = [
        'activity_logs',
    ];

    /**
     * The role the application connects as must hold none of the privileges
     * that exempt it from the policies.
     *
     * Without this, pointing DB_APP_USERNAME at `postgres` would leave every
     * other test in this file passing while enforcing nothing at all.
     */
    public function test_the_application_role_cannot_bypass_the_policies(): void
    {
        $this->bootstrapErpContext();

        $role = config('database.connections.pgsql_app.username');

        $privileges = DB::table('pg_roles')
            ->where('rolname', $role)
            ->first(['rolsuper', 'rolbypassrls', 'rolcreaterole']);

        $this->assertNotNull($privileges, "The '{$role}' role does not exist.");
        $this->assertFalse((bool) $privileges->rolsuper, "'{$role}' is a superuser and bypasses every policy.");
        $this->assertFalse((bool) $privileges->rolbypassrls, "'{$role}' holds BYPASSRLS.");
        $this->assertFalse((bool) $privileges->rolcreaterole, "'{$role}' can create roles.");
    }

    /**
     * Every table that carries a company has row security on and a policy
     * attached, except the two that are allowed not to.
     *
     * Asserted against the schema rather than a list, so a table added by a
     * later migration is covered the moment it gets a `company_id` — the whole
     * point of the migration discovering its own scope. A new table that must
     * stay unprotected fails here until the decision is written down in both
     * places.
     *
     * Both halves are checked per table: row security without a policy denies
     * everything, and a policy without row security denies nothing.
     */
    public function test_every_company_table_has_row_security_enabled(): void
    {
        $this->bootstrapErpContext();

        $tables = DB::table('pg_class as c')
            ->join('pg_namespace as n', 'n.oid', '=', 'c.relnamespace')
            ->join('pg_attribute as a', 'a.attrelid', '=', 'c.oid')
            ->where('n.nspname', 'public')
            ->where('c.relkind', 'r')
            ->where('a.attname', 'company_id')
            ->where('a.attnum', '>', 0)
            ->where('a.attisdropped', false)
            ->orderBy('c.relname')
            ->pluck('c.relname')
            ->reject(fn (string $table) => in_array($table, self::UNPROTECTED_TABLES, true));

        $this->assertGreaterThan(
            90,
            $tables->count(),
            'Far fewer company tables than expected — the query is probably wrong, not the schema.'
        );

        foreach ($tables as $table) {
            $enabled = DB::scalar(
                'SELECT relrowsecurity FROM pg_class WHERE oid = ?::regclass',
                [$table]
            );

            $this->assertTrue(
                (bool) $enabled,
                "Row level security is not enabled on '{$table}'."
            );

            // The two asymmetric shapes split the rule across four policies,
            // one per command, so there is no single `_isolation` policy to
            // look for; `_read` is the one every shape has.
            $splitRule = in_array($table, self::SHARED_REFERENCE_TABLES, true)
                || in_array($table, self::AUDIT_TRAIL_TABLES, true);

            $expected = $splitRule ? "{$table}_company_read" : "{$table}_company_isolation";

            $this->assertTrue(
                DB::table('pg_policies')->where('tablename', $table)->where('policyname', $expected)->exists(),
                "'{$table}' has no company policy named '{$expected}'."
            );
        }
    }

    /**
     * Shared reference data is readable by every company, writable by none.
     *
     * ReferenceDataSeeder writes the standard customer groups and payment terms
     * once with no company. Every company has to be able to read that list, and
     * none of them may change it — including by deleting it, which is why the
     * rule is four policies rather than one: a DELETE is checked against USING
     * alone, so a rule permissive enough to read the shared rows would be
     * permissive enough to remove them.
     */
    public function test_shared_reference_rows_are_readable_but_not_writable(): void
    {
        $context = $this->bootstrapErpContext();

        $sharedId = (string) Str::ulid();

        // Written as the owner, the way the seeder does it.
        $this->asSchemaOwner(fn () => DB::table('payment_terms')->insert([
            'id' => $sharedId,
            'company_id' => null,
            'name' => 'Net 90 (shared)',
            'days' => 90,
            'type' => 'net',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $this->asApplicationRole($context['company']->id, function () use ($sharedId) {
            $this->assertTrue(
                DB::table('payment_terms')->where('id', $sharedId)->exists(),
                'A company cannot see the shared payment terms.'
            );

            $this->assertSame(
                0,
                DB::table('payment_terms')->where('id', $sharedId)->delete(),
                'A company was able to delete a shared payment term.'
            );

            $this->assertSame(
                0,
                DB::table('payment_terms')->where('id', $sharedId)->update(['days' => 1]),
                'A company was able to change a shared payment term.'
            );

            $this->assertRejected(
                fn () => DB::table('payment_terms')->insert([
                    'id' => (string) Str::ulid(),
                    'company_id' => null,
                    'name' => 'Net 120 (smuggled)',
                    'days' => 120,
                    'type' => 'net',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
                'A company was able to add a payment term to the shared list.'
            );
        });

        $this->assertSame(
            90,
            (int) DB::table('payment_terms')->where('id', $sharedId)->value('days'),
            'The shared payment term was modified.'
        );
    }

    /**
     * The headline case: a query with no company filter at all still cannot
     * see another company's rows.
     *
     * This is the bug class the whole of phase 2 kept finding — a hand-written
     * report query that forgot `where company_id`. Before this step it returned
     * every company's rows; the assertion is that it now returns one company's.
     */
    public function test_a_query_without_a_company_filter_sees_only_its_own_company(): void
    {
        $context = $this->bootstrapErpContext();
        $ownCompanyId = $context['company']->id;

        $otherAccount = $this->accountInAnotherCompany();

        $ownCount = Account::withoutGlobalScopes()->where('company_id', $ownCompanyId)->count();
        $this->assertGreaterThan(0, $ownCount, 'The fixture company has no accounts to compare against.');

        $this->asApplicationRole($ownCompanyId, function () use ($ownCount, $otherAccount, $ownCompanyId) {
            // Deliberately no company filter and no global scope: this is the
            // shape of the queries that used to leak.
            $rows = DB::table('accounts')->get(['id', 'company_id']);

            $this->assertCount($ownCount, $rows);
            $this->assertNotContains(
                $otherAccount->id,
                $rows->pluck('id')->all(),
                'The other company\'s account was visible to an unfiltered query.'
            );
            $this->assertSame(
                [$ownCompanyId],
                $rows->pluck('company_id')->map(fn ($id) => trim((string) $id))->unique()->values()->all()
            );
        });
    }

    /**
     * With no company on the connection, the protected tables are empty.
     *
     * This is the decided behaviour for anything running outside a request: a
     * job that forgot to say which books it is working in gets nothing back,
     * rather than every company's rows.
     */
    public function test_without_a_company_on_the_connection_nothing_is_visible(): void
    {
        $this->bootstrapErpContext();

        $this->asApplicationRole(null, function () {
            $this->assertSame(0, DB::table('accounts')->count());
            $this->assertSame(0, DB::table('items')->count());
            $this->assertSame(0, DB::table('ledgers')->count());
        });
    }

    /**
     * A row cannot be written into another company's books.
     *
     * Without WITH CHECK the application role could insert a row stamped with
     * someone else's company id: invisible to itself afterwards, but sitting in
     * the other company's ledger.
     */
    public function test_a_row_cannot_be_inserted_into_another_company(): void
    {
        $context = $this->bootstrapErpContext();
        $otherCompanyId = Company::factory()->create()->id;
        $accountTypeId = $context['account_types']['expense']->id;

        $this->asApplicationRole($context['company']->id, function () use ($otherCompanyId, $accountTypeId) {
            $this->assertRejected(
                fn () => DB::table('accounts')->insert([
                    'id' => (string) Str::ulid(),
                    'company_id' => $otherCompanyId,
                    'account_type_id' => $accountTypeId,
                    'name' => 'Smuggled Account',
                    'number' => '99991',
                    'is_active' => true,
                    'is_main' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
                'An account was inserted into another company.'
            );
        });
    }

    /**
     * An existing row cannot be moved into another company either.
     *
     * UPDATE is checked twice — USING picks which rows are reachable, WITH
     * CHECK picks what they may become — and only the second half stops a
     * reachable row from being re-stamped.
     */
    public function test_a_row_cannot_be_moved_into_another_company(): void
    {
        $context = $this->bootstrapErpContext();
        $ownCompanyId = $context['company']->id;
        $otherCompanyId = Company::factory()->create()->id;
        $accountId = $context['accounts']['cash-in-hand']->id;

        $this->asApplicationRole($ownCompanyId, function () use ($accountId, $otherCompanyId) {
            $this->assertRejected(
                fn () => DB::table('accounts')->where('id', $accountId)->update(['company_id' => $otherCompanyId]),
                'An account was moved into another company.'
            );
        });

        $this->assertSame(
            $ownCompanyId,
            trim((string) DB::table('accounts')->where('id', $accountId)->value('company_id'))
        );
    }

    /**
     * Another company's row cannot be reached for update or delete.
     *
     * An invisible row has to be unreachable as well: a DELETE that matched on
     * primary key would otherwise quietly remove a row the caller could not see.
     */
    public function test_another_companys_row_cannot_be_updated_or_deleted(): void
    {
        $context = $this->bootstrapErpContext();
        $otherAccount = $this->accountInAnotherCompany();

        $this->asApplicationRole($context['company']->id, function () use ($otherAccount) {
            // No error: the row simply is not there to match. Postgres applies
            // the policy as a filter on what the statement can touch.
            $this->assertSame(0, DB::table('accounts')->where('id', $otherAccount->id)->delete());
            $this->assertSame(0, DB::table('accounts')->where('id', $otherAccount->id)->update(['name' => 'Renamed']));
        });

        // Read back from inside the rival company: under the policies the row
        // is unreachable from anywhere else, so "is it still there" can only be
        // asked by the company that owns it.
        $survivor = CompanyDatabaseContext::runFor(
            $otherAccount->company_id,
            fn () => Account::withoutGlobalScopes()->find($otherAccount->id)
        );

        $this->assertNotNull($survivor, 'Another company\'s account was deleted.');
        $this->assertSame($otherAccount->name, $survivor->name);
    }

    /**
     * The application role cannot switch the policies off.
     *
     * This is why the schema is owned by a different role: `ALTER TABLE ...
     * DISABLE ROW LEVEL SECURITY` is an owner privilege, so SQL injection in
     * application code cannot lift the boundary it is confined by.
     */
    public function test_the_application_role_cannot_disable_the_policies(): void
    {
        $this->bootstrapErpContext();

        $this->asApplicationRole(null, function () {
            $this->assertRejected(
                fn () => DB::statement('ALTER TABLE accounts DISABLE ROW LEVEL SECURITY'),
                'The application role was able to disable row level security.'
            );
        });
    }

    /**
     * Assert that Postgres refuses a statement.
     *
     * The savepoint matters. The suite runs each test inside one transaction,
     * and a rejected statement puts that transaction into an aborted state
     * where every later command — including the RESET ROLE in the caller's
     * `finally`, and RefreshDatabase's own cleanup — fails with "current
     * transaction is aborted". Laravel implements a nested `transaction()` as a
     * SAVEPOINT and rolls back to it, which leaves the outer transaction usable.
     *
     * The SQLSTATE is asserted rather than merely the exception type, because
     * every one of these statements is otherwise valid: a unique violation or a
     * missing column would also raise QueryException and would make the test
     * pass for the wrong reason. 42501 is insufficient_privilege, which is what
     * both "new row violates row-level security policy" and "must be owner of
     * table" report.
     */
    private function assertRejected(callable $statement, string $message): void
    {
        try {
            DB::transaction($statement);
        } catch (QueryException $e) {
            $this->assertSame(
                '42501',
                (string) $e->getCode(),
                "Statement was rejected, but not for want of privilege: {$e->getMessage()}"
            );

            return;
        }

        $this->fail($message);
    }

    /**
     * The audit trail accepts an event it cannot attribute, and shows it to
     * nobody.
     *
     * ActivityLogService falls back to a null company when nothing establishes
     * one — a failed sign-in, or anything done before the user has a company,
     * including creating the user. Refusing those inserts breaks user creation
     * outright, which is what a plain equality policy did. But an unattributed
     * event can name another company's user, so it must not become readable by
     * every tenant either: the asymmetry is deliberate and runs the opposite
     * way to the shared reference tables.
     */
    public function test_an_unattributed_audit_event_can_be_written_but_not_read(): void
    {
        $context = $this->bootstrapErpContext();

        $this->asApplicationRole($context['company']->id, function () {
            $id = (string) Str::ulid();

            DB::table('activity_logs')->insert([
                'id' => $id,
                'company_id' => null,
                'event_type' => 'login_failed',
                'module' => 'auth',
                'description' => 'Unattributed event',
                'created_at' => now(),
            ]);

            $this->assertFalse(
                DB::table('activity_logs')->where('id', $id)->exists(),
                'An unattributed audit event was readable by a company.'
            );

            // Visible to the owner, which is where it is investigated from.
            $this->assertTrue(
                $this->asSchemaOwner(fn () => DB::table('activity_logs')->where('id', $id)->exists()),
                'An unattributed audit event was not written at all.'
            );
        });
    }

    /**
     * A web request leaves its company on the connection.
     *
     * The policies are only as good as the thing that supplies the value they
     * read. This asserts the wiring — SetActiveCompany resolves the company,
     * SetDatabaseCompanyContext hands it to Postgres — rather than the policies
     * themselves, and it is the one test that would fail if that middleware
     * were dropped from the stack.
     */
    public function test_a_web_request_sets_the_company_on_the_connection(): void
    {
        $context = $this->bootstrapErpContext();

        CompanyDatabaseContext::forget();

        $this->actingAs($context['user'])->get(route('dashboard'))->assertSuccessful();

        $this->assertSame(
            $context['company']->id,
            CompanyDatabaseContext::current(),
            'The request did not hand its company to the database.'
        );
    }

    /**
     * CompanyDatabaseContext::runFor restores whatever company was set before.
     *
     * The notification jobs loop over companies inside one worker process; if a
     * failure left the connection pointed at the last company it touched, the
     * next thing on that connection would read the wrong books.
     */
    public function test_run_for_restores_the_previous_company(): void
    {
        $context = $this->bootstrapErpContext();
        $ownCompanyId = $context['company']->id;
        $otherCompanyId = Company::factory()->create()->id;

        CompanyDatabaseContext::set($ownCompanyId);

        $seen = CompanyDatabaseContext::runFor(
            $otherCompanyId,
            fn () => CompanyDatabaseContext::current()
        );

        $this->assertSame($otherCompanyId, $seen);
        $this->assertSame($ownCompanyId, CompanyDatabaseContext::current());

        try {
            CompanyDatabaseContext::runFor($otherCompanyId, function () {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // Expected.
        }

        $this->assertSame(
            $ownCompanyId,
            CompanyDatabaseContext::current(),
            'A throwing callback left the connection pointed at the wrong company.'
        );

        CompanyDatabaseContext::forget();
    }

    /**
     * Run a callback with one company — or none — on the connection.
     *
     * The role itself is not switched here: `Tests\TestCase` already puts every
     * test under the application role, so what varies between these cases is
     * only which company the connection is acting for. The previous company is
     * put back afterwards even when the callback fails.
     */
    private function asApplicationRole(?string $companyId, callable $callback): void
    {
        CompanyDatabaseContext::runFor($companyId, $callback);
    }

    /**
     * An account belonging to a company the current user has nothing to do with.
     *
     * Built with the connection pointed at that other company, because the
     * policy's WITH CHECK refuses a row stamped with any company but the one on
     * the connection — the fixture has to be created the same way the rival
     * company's own users would create it. Doing it as the schema owner instead
     * would work, but would prove less: this way the row is known to have been
     * written through the policies, not around them.
     */
    private function accountInAnotherCompany(): Account
    {
        $company = Company::factory()->create(['name_en' => 'Rival Company']);

        return CompanyDatabaseContext::runFor($company->id, fn (): Account => Account::factory()->create([
            'company_id' => $company->id,
            'name' => 'Rival Cash',
            'number' => '91001',
        ]));
    }
}

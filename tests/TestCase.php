<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Refuse to run against anything that is not a dedicated test database.
     *
     * The whole suite runs under RefreshDatabase, which migrates fresh — it
     * drops every table before the first test. If `.env.testing` is missing,
     * APP_ENV=testing silently falls back to `.env`, and the suite empties the
     * development database instead. That has happened; this is the guard.
     *
     * The name has to opt in rather than merely differ from the dev database,
     * because the fallback case has no dev name to compare against.
     */
    protected function setUp(): void
    {
        // The application has to exist before the guard can read config(), but
        // the guard has to run before RefreshDatabase migrates. parent::setUp()
        // does both in that order and offers no hook between them, so the app is
        // built here first — parent::setUp() then skips creating it (it only
        // calls refreshApplication() when $this->app is null) and goes straight
        // on to the traits.
        //
        // Calling guardTestDatabase() before this line resolved 'config' from an
        // empty container and failed every test in the suite with
        // "Target class [config] does not exist".
        if (! $this->app) {
            $this->refreshApplication();
        }

        $this->guardTestDatabase();

        parent::setUp();

        // After parent::setUp(), so the migration and the per-test transaction
        // are the schema owner's; before the child class builds its fixtures,
        // so those are written under the policies like everything else.
        $this->actAsApplicationRole();
    }

    /**
     * Run the body of every test under the role the application actually uses,
     * so the row level security policies are in force.
     *
     * Without this the suite proves nothing about the policies: it connects as
     * the schema owner, which Postgres exempts from them, so a code path that
     * forgets to set `app.company_id` returns every row in the test and no rows
     * in production. Four such paths were found by hand during step 10; this is
     * what finds the rest.
     *
     * ## Why SET ROLE and not a second connection
     *
     * Pointing the suite's default connection at `pgsql_app` does not work, for
     * two independent reasons:
     *
     *  - `pgsql_app` holds no DDL privileges, so `migrate:fresh` would fail.
     *    RefreshDatabase exposes `migrateFreshUsing()` for redirecting that,
     *    but every test class `use`s the trait itself, and a trait method in
     *    the child class takes precedence over an inherited one — so an
     *    override here would never be called.
     *  - Switching the connection after `parent::setUp()` would leave the
     *    per-test transaction on the old one. Fixtures would be written outside
     *    it, committed, and never rolled back.
     *
     * `SET ROLE` sidesteps both. The connection, the session and the
     * transaction stay exactly as they were; only the role Postgres evaluates
     * the policies against changes. Migrations and the transaction are the
     * owner's; everything the test does afterwards is the application's.
     *
     * It has to run *after* `parent::setUp()`, which is what opens the
     * transaction, and before the child class's own fixtures are built.
     */
    private function actAsApplicationRole(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $role = (string) config('database.connections.pgsql_app.username');

        // Created by 2026_10_21_000001_enable_row_level_security, which
        // migrate:fresh has just run. Missing it would mean the policies are
        // not installed either, so failing loudly beats running unprotected.
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $role)) {
            throw new RuntimeException("DB_APP_USERNAME is not a usable Postgres role name: '{$role}'.");
        }

        $connection->statement("SET ROLE {$role}");
    }

    /**
     * Run a callback as the schema owner, then go back to the application role.
     *
     * For the handful of tests that replay a migration: recreating a table a
     * later migration drops, or re-adding a check constraint to prove the
     * migration that changed it works. DDL is an owner privilege, and in
     * production that code path *is* a migration, so running it as the owner
     * is faithful rather than a way around the policies.
     *
     * Reach for this only for DDL. A test that cannot write a row is not
     * hitting a privilege problem — it is hitting the company boundary, and the
     * fix is to say which company it is acting for.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    protected function asSchemaOwner(callable $callback): mixed
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $callback();
        }

        DB::statement('RESET ROLE');

        try {
            return $callback();
        } finally {
            $this->actAsApplicationRole();
        }
    }

    protected function tearDown(): void
    {
        // The transaction rollback resets the role on its own, since the SET
        // happened inside it. This is for the case where the rollback did not
        // get that far, so one broken test does not report as many.
        try {
            DB::statement('RESET ROLE');
        } catch (Throwable) {
            // Connection already gone; nothing to reset.
        }

        parent::tearDown();
    }

    private function guardTestDatabase(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        // In-memory SQLite is disposable by definition.
        if ($database === ':memory:' || $database === '') {
            return;
        }

        if (str_contains(strtolower(basename($database)), 'test')) {
            return;
        }

        throw new RuntimeException(
            "Refusing to run tests against the '{$database}' database: the name does not "
            . "identify it as a test database, and the suite would migrate fresh over it. "
            . "Create a .env.testing pointing DB_DATABASE at a disposable database "
            . "(the project uses 'nextbook_test'). See TESTING_GUIDE.md."
        );
    }
}

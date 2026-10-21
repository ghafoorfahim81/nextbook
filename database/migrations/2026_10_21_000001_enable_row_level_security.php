<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 10 of the multi-company plan: make Postgres itself refuse to hand one
 * company another company's rows.
 *
 * Everything before this step moved the company boundary into the application:
 * a column on every table, a global scope on every model, a `where company_id`
 * on every hand-written query. That boundary holds only as long as every query
 * remembers it, and the migration that got us here found four separate classes
 * of bug where one did not — see docs/phase-2-handoff.md, section 3. A missed
 * filter is a silent cross-company read, and nothing in PHP catches it.
 *
 * Row level security turns that from an application invariant into a database
 * one. A query that forgets the filter now returns too few rows instead of too
 * many, which is a bug that gets reported rather than one that gets exploited.
 *
 * ## Why this needs a second role
 *
 * Postgres exempts two kinds of role from the policies below: superusers, and a
 * table's own owner (unless the table is set to FORCE). The application has
 * always connected as `postgres`, a superuser, so installing policies without
 * also changing who connects would achieve precisely nothing — and the tests
 * proving isolation would pass against no enforcement at all.
 *
 * So the schema stays owned by the migrating role and the application moves to
 * `nextbook_app`, which is neither a superuser nor BYPASSRLS and owns nothing.
 * The split is deliberate in both directions:
 *
 *  - The app role cannot turn the policies off. `ALTER TABLE ... DISABLE ROW
 *    LEVEL SECURITY` is an owner privilege, so SQL injection in the application
 *    cannot lift the boundary it is confined by.
 *
 *  - The owner is not subject to the policies, so backfill migrations keep
 *    working. A migration that has to rewrite rows across every company — like
 *    2026_10_19_000001_create_ledger_branches_table does — needs exactly that
 *    exemption, and getting it by being the owner is simpler and harder to
 *    misuse than an escape hatch written into the policies.
 *
 * ENABLE rather than FORCE ROW LEVEL SECURITY is the point of that second
 * bullet: FORCE would subject the owner to the policies too and break every
 * cross-company migration and maintenance query.
 *
 * ## Scope
 *
 * Nine tables, chosen as the ones that hold money and stock. They are the rows
 * that must never cross a company boundary, and they exercise every shape the
 * remaining tables have: a document header, its lines, a sub-ledger, a chart of
 * accounts, master data, and two stock tables. Widening to the rest is a
 * repetition of this file, not a new design.
 *
 * `companies`, `branches` and `users` are deliberately left out: sign-in and
 * branch switching read them before any company has been established, and a
 * policy there would lock everyone out of the application.
 */
return new class extends Migration
{
    /**
     * The tables the policies are installed on.
     *
     * Every one of these carries a `company_id` that is the company the row
     * belongs to, not one derived from a parent — `transaction_lines` got its
     * own column in step 6 for exactly this reason.
     */
    private const TABLES = [
        'transactions',
        'transaction_lines',
        'sales',
        'purchases',
        'ledgers',
        'accounts',
        'items',
        'stock_movements',
        'stock_balances',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $role = $this->appRole();

        $this->ensureRoleExists($role);
        $this->grantApplicationPrivileges($role);

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException(
                    "Cannot enable row level security on '{$table}': the table does not exist. "
                    . 'This migration must run after the migrations that create it.'
                );
            }

            $this->installPolicy($table);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement("DROP POLICY IF EXISTS {$table}_company_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }

        // The role itself is left in place. Dropping it would break any other
        // environment still connecting with it, and a login role that owns
        // nothing is harmless once the policies are gone.
    }

    /**
     * Enable row level security on one table and install its policy.
     *
     * The policy is a single `FOR ALL` rule carrying both halves:
     *
     *  - USING decides which existing rows the role can see, and therefore
     *    which it can UPDATE or DELETE.
     *  - WITH CHECK decides which rows it may write. Without it the role could
     *    insert a row stamped with another company's id — invisible to itself
     *    afterwards, but sitting in the other company's books.
     *
     * `current_setting('app.company_id', true)` takes `true` as its second
     * argument so an unset variable yields NULL rather than raising. NULL
     * compares unequal to every id, so a connection that never said which
     * company it is acting for sees nothing and may write nothing. That is the
     * decided behaviour: a job that forgot its company gets an empty result
     * set, not every company's data.
     *
     * The cast is on the setting rather than on the column. Casting
     * `company_id::text` instead would make the comparison unindexable and turn
     * every query on these tables into a sequential scan; `current_setting` is
     * STABLE, so with the cast on this side Postgres folds it to a constant and
     * the existing `(company_id, ...)` indexes still apply.
     */
    private function installPolicy(string $table): void
    {
        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");

        // Idempotent so the migration can be re-run against a database that
        // already has it, and so `down()` followed by `up()` is clean.
        DB::statement("DROP POLICY IF EXISTS {$table}_company_isolation ON {$table}");

        DB::statement(
            "CREATE POLICY {$table}_company_isolation ON {$table}
                FOR ALL
                USING (company_id = current_setting('app.company_id', true)::char(26))
                WITH CHECK (company_id = current_setting('app.company_id', true)::char(26))"
        );
    }

    /**
     * The role the application connects as, read from the `pgsql_app`
     * connection so the policies can never be granted to a different role than
     * the one the application actually uses.
     */
    private function appRole(): string
    {
        $role = (string) config('database.connections.pgsql_app.username');

        // Identifiers cannot be bound as parameters, so this value is
        // interpolated into DDL below. Postgres role names are restricted to
        // this alphabet anyway; rejecting anything else keeps a stray character
        // in .env from becoming an injection point in a migration that runs as
        // the schema owner.
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $role)) {
            throw new RuntimeException(
                "DB_APP_USERNAME is not a usable Postgres role name: '{$role}'."
            );
        }

        return $role;
    }

    /**
     * Create the application role when it is missing and we are allowed to.
     *
     * On a developer machine and in the test database the migrating role is a
     * superuser, so `migrate:fresh` can create the role itself and the suite
     * needs no manual setup. On staging and production the role is created
     * ahead of time by database/sql/nextbook_app_role.sql, with a password that
     * never passes through this repository — so if it already exists this does
     * nothing, and in particular never resets its password.
     */
    private function ensureRoleExists(string $role): void
    {
        $exists = DB::scalar('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role]);

        if ($exists) {
            return;
        }

        $mayCreateRoles = (bool) DB::scalar(
            'SELECT rolsuper OR rolcreaterole FROM pg_roles WHERE rolname = current_user'
        );

        if (! $mayCreateRoles) {
            throw new RuntimeException(
                "The '{$role}' role does not exist and the migrating role cannot create it. "
                . 'Run database/sql/nextbook_app_role.sql as a superuser first; it is the '
                . 'documented production step and sets the password this repository never sees.'
            );
        }

        $password = (string) config('database.connections.pgsql_app.password');

        if ($password === '') {
            throw new RuntimeException(
                "Refusing to create the '{$role}' role without a password. Set DB_APP_PASSWORD."
            );
        }

        // NOBYPASSRLS and NOSUPERUSER are the whole point of the role; the rest
        // is denied because an application login has no business holding it.
        DB::statement(
            "CREATE ROLE {$role} LOGIN NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE "
            . 'NOREPLICATION PASSWORD ' . $this->quote($password)
        );
    }

    /**
     * Give the application role the privileges it needs and nothing more.
     *
     * No DDL, no ownership: the role may read and write rows, and that is all.
     * The default privileges at the end cover tables added by later migrations,
     * so a new table is reachable by the application without a second grant —
     * but note it arrives *without* a policy, so step 10's remaining work is to
     * extend TABLES above rather than to remember a GRANT.
     */
    private function grantApplicationPrivileges(string $role): void
    {
        $schema = 'public';

        DB::statement("GRANT USAGE ON SCHEMA {$schema} TO {$role}");
        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA {$schema} TO {$role}");

        // ULID primary keys need no sequence, but Laravel's own tables (jobs,
        // failed_jobs, cache locks) still use them, and a missing sequence
        // grant surfaces as a queue that cannot enqueue.
        DB::statement("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA {$schema} TO {$role}");

        DB::statement(
            "ALTER DEFAULT PRIVILEGES IN SCHEMA {$schema} "
            . "GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$role}"
        );
        DB::statement(
            "ALTER DEFAULT PRIVILEGES IN SCHEMA {$schema} GRANT USAGE, SELECT ON SEQUENCES TO {$role}"
        );
    }

    /**
     * Quote a password as a Postgres string literal.
     *
     * CREATE ROLE will not take a bound parameter for its password, so the
     * value has to be inlined. Doubling single quotes is the whole of the
     * escaping rule for a standard-conforming string literal.
     */
    private function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
};

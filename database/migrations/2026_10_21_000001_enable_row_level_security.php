<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
 * Every table carrying a `company_id`, discovered from the schema rather than
 * listed by hand. A list would have to be remembered: a table added by a later
 * migration would reach the application without a policy, and the only symptom
 * would be the absence of one. Deriving it means a new company-scoped table is
 * protected by having the column, and a table that must *not* be protected has
 * to say so in EXCLUDED_TABLES, where the reason can be written down.
 *
 * Two kinds of exception, both below:
 *
 *  - EXCLUDED_TABLES — read before a company exists, so a policy would lock
 *    everyone out.
 *  - SHARED_REFERENCE_TABLES — seeded once with no company and shared by all of
 *    them, so a plain equality policy would hide every row from everyone.
 */
return new class extends Migration
{
    /**
     * Tables that carry a company but must not be filtered by one.
     *
     * Each of these is read before the request has established which company it
     * is acting for, so a policy on them would deny the step that establishes
     * it and lock everyone out of the application:
     *
     *  - `users` — read by the authentication guard, before sign-in resolves a
     *    company at all. Its own `company_id` is nullable for the same reason:
     *    a user exists before their company does.
     *  - `branches` — read by SetActiveBranch, which validates the requested
     *    branch against the company, and by the branch switcher. Cross-company
     *    reads here are already prevented at the application level by
     *    CompanySpecific on the Branch model, with a test holding it in place
     *    (tests/Feature/Tenancy/CompanyScopeTest.php).
     */
    private const EXCLUDED_TABLES = [
        'users',
        'branches',
    ];

    /**
     * Tables holding reference data every company shares.
     *
     * ReferenceDataSeeder writes these once with `company_id` null — the
     * standard customer groups and payment terms, the same list for everyone —
     * and the nullable column lets a company add its own alongside. A plain
     * equality policy would therefore hide the entire shared list from every
     * company, which is why these get their own rule: read what is shared or
     * your own, write only your own.
     */
    private const SHARED_REFERENCE_TABLES = [
        'customer_groups',
        'payment_terms',
    ];

    /**
     * The audit trail, which has to accept events it cannot attribute.
     *
     * `activity_logs.company_id` is nullable because ActivityLogService falls
     * back to null when nothing establishes a company — a failed sign-in, or
     * anything a user does before they have a company, including the creation
     * of the user themselves. Under a plain equality policy that insert is
     * refused, which breaks user creation outright.
     *
     * So writes may be unattributed, but reads may not see what is not theirs:
     * an unattributed event can carry another company's email address or failed
     * login, and a tenant has no business reading it. Those rows stay visible
     * on the owner connection, which is where an administrator investigating
     * them looks.
     *
     * This is the opposite asymmetry to SHARED_REFERENCE_TABLES, where the
     * company-less rows are readable by everyone and writable by no one.
     */
    private const AUDIT_TRAIL_TABLES = [
        'activity_logs',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $role = $this->appRole();

        $this->ensureRoleExists($role);
        $this->grantApplicationPrivileges($role);

        foreach ($this->companyTables() as $table) {
            match (true) {
                in_array($table, self::SHARED_REFERENCE_TABLES, true) => $this->installSharedReferencePolicies($table),
                in_array($table, self::AUDIT_TRAIL_TABLES, true) => $this->installAuditTrailPolicies($table),
                default => $this->installPolicy($table),
            };
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Driven off the policies that actually exist rather than off the
        // discovery above, so a rollback cleans up after a schema that has since
        // changed — including a table that has lost its company column.
        $policies = DB::select(
            'SELECT tablename, policyname FROM pg_policies WHERE schemaname = current_schema()'
        );

        $tables = [];

        foreach ($policies as $policy) {
            DB::statement("DROP POLICY IF EXISTS {$policy->policyname} ON {$policy->tablename}");
            $tables[$policy->tablename] = true;
        }

        foreach (array_keys($tables) as $table) {
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }

        // The role itself is left in place. Dropping it would break any other
        // environment still connecting with it, and a login role that owns
        // nothing is harmless once the policies are gone.
    }

    /**
     * Every table in the schema that carries a company, minus the exclusions.
     *
     * Read from the catalogue rather than listed here: a table added by a later
     * migration is then protected by virtue of having the column, and cannot
     * arrive without a policy because nobody remembered to add it to a list.
     *
     * @return array<int, string>
     */
    private function companyTables(): array
    {
        $tables = DB::select(
            "SELECT c.relname AS name
             FROM pg_class c
             JOIN pg_namespace n ON n.oid = c.relnamespace
             JOIN pg_attribute a ON a.attrelid = c.oid
             WHERE n.nspname = current_schema()
               AND c.relkind = 'r'
               AND a.attname = 'company_id'
               AND a.attnum > 0
               AND NOT a.attisdropped
             ORDER BY c.relname"
        );

        $names = array_map(fn (object $row) => $row->name, $tables);

        return array_values(array_diff($names, self::EXCLUDED_TABLES));
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
     * Install the rule for a table of shared reference data.
     *
     * The shape is "read what is shared or your own, write only your own", and
     * it needs four policies rather than one because the two halves of a single
     * `FOR ALL` rule do not line up with that:
     *
     *  - A row with no company has to be *visible* to everyone, so SELECT's
     *    USING must admit NULL.
     *  - But nobody may *write* one, or a company could edit the list everyone
     *    else reads. So the write rules exclude NULL on both sides.
     *
     * DELETE is why USING has to be split out rather than shared. A DELETE is
     * checked against USING alone — there is no new row to check — so a single
     * rule permissive enough for SELECT would also let any company delete the
     * shared rows out from under every other company.
     *
     * Postgres combines permissive policies for the same command with OR, and
     * these four cover disjoint commands, so each command gets exactly one rule.
     */
    private function installSharedReferencePolicies(string $table): void
    {
        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");

        $own = "company_id = current_setting('app.company_id', true)::char(26)";
        $sharedOrOwn = "company_id IS NULL OR {$own}";

        foreach (['read', 'insert', 'update', 'delete'] as $suffix) {
            DB::statement("DROP POLICY IF EXISTS {$table}_company_{$suffix} ON {$table}");
        }

        DB::statement("CREATE POLICY {$table}_company_read ON {$table} FOR SELECT USING ({$sharedOrOwn})");
        DB::statement("CREATE POLICY {$table}_company_insert ON {$table} FOR INSERT WITH CHECK ({$own})");
        DB::statement("CREATE POLICY {$table}_company_update ON {$table} FOR UPDATE USING ({$own}) WITH CHECK ({$own})");
        DB::statement("CREATE POLICY {$table}_company_delete ON {$table} FOR DELETE USING ({$own})");
    }

    /**
     * Install the rule for the audit trail: write what you cannot attribute,
     * read only what is yours.
     *
     * The INSERT side admits a null company because the service genuinely
     * cannot always name one — see AUDIT_TRAIL_TABLES. The SELECT side does
     * not, because an unattributed event may describe another company's user.
     * UPDATE and DELETE stay restricted to the company's own rows; the
     * retention sweep that removes old entries runs on the owner connection.
     */
    private function installAuditTrailPolicies(string $table): void
    {
        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");

        $own = "company_id = current_setting('app.company_id', true)::char(26)";
        $ownOrUnattributed = "company_id IS NULL OR {$own}";

        foreach (['read', 'insert', 'update', 'delete'] as $suffix) {
            DB::statement("DROP POLICY IF EXISTS {$table}_company_{$suffix} ON {$table}");
        }

        DB::statement("CREATE POLICY {$table}_company_read ON {$table} FOR SELECT USING ({$own})");
        DB::statement("CREATE POLICY {$table}_company_insert ON {$table} FOR INSERT WITH CHECK ({$ownOrUnattributed})");
        DB::statement("CREATE POLICY {$table}_company_update ON {$table} FOR UPDATE USING ({$own}) WITH CHECK ({$own})");
        DB::statement("CREATE POLICY {$table}_company_delete ON {$table} FOR DELETE USING ({$own})");
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

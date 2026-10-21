<?php

namespace App\Console\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Refuses to run a maintenance command on a connection the row level security
 * policies apply to.
 *
 * These commands sweep every company by design — re-deriving average costs,
 * verifying that transactions balance, backfilling a new table. On the
 * application's own connection (`nextbook_app`, which the policies filter)
 * their queries would match nothing, and they would report "0 rows processed"
 * and exit successfully. An operator reads that as "nothing was wrong".
 *
 * So the failure is made loud instead. The check is a privilege lookup rather
 * than a guess at the connection name: what matters is whether the role
 * Postgres is evaluating policies against can see past them, which is exactly
 * what `rolsuper OR rolbypassrls` answers — and a table's owner, which is the
 * case this project actually relies on, is covered by the ownership test below.
 */
trait RequiresCrossCompanyAccess
{
    /**
     * True when the command may proceed; otherwise it has already explained
     * why not and the caller should return Command::FAILURE.
     */
    protected function hasCrossCompanyAccess(): bool
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            return true;
        }

        // Nothing to be filtered by.
        $anyPolicies = (bool) $connection->scalar(
            "SELECT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname = current_schema())"
        );

        if (! $anyPolicies) {
            return true;
        }

        $exempt = (bool) $connection->scalar(
            'SELECT rolsuper OR rolbypassrls FROM pg_roles WHERE rolname = current_user'
        );

        // Owning the tables is the ordinary case: migrations and these commands
        // connect as the schema owner, and Postgres does not apply a table's
        // policies to its owner unless the table is set to FORCE.
        $ownsProtectedTables = (bool) $connection->scalar(
            "SELECT EXISTS (
                SELECT 1 FROM pg_policies p
                JOIN pg_class c ON c.relname = p.tablename
                JOIN pg_roles r ON r.oid = c.relowner
                WHERE p.schemaname = current_schema() AND r.rolname = current_user
            )"
        );

        if ($exempt || $ownsProtectedTables) {
            return true;
        }

        $this->components->error(
            'This command reads across every company, but the current database role is '
            . 'subject to the row level security policies, so it would find nothing and '
            . 'report success. Run it on the owner connection instead:'
        );
        $this->line('');
        $this->line('    DB_CONNECTION=pgsql php artisan ' . $this->getName());
        $this->line('');

        return false;
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tells Postgres which company the current connection is acting for.
 *
 * Row level security policies (step 10 of the multi-company plan) read
 * `app.company_id` off the session and hide every row belonging to another
 * company. The policies are the enforcement; this class is the only place that
 * supplies the value they read.
 *
 * Two rules decide everything here:
 *
 *  - **Unset means nothing is visible.** The policies compare for equality, so
 *    an unset — or empty — setting matches no row and permits no insert. That
 *    is the decided behaviour: a job or console command that forgot to say
 *    which books it is writing in gets an empty result set rather than every
 *    company's data. Anything running outside a request therefore has to call
 *    `runFor()` explicitly.
 *
 *  - **The company is never taken from input.** Callers pass an id that was
 *    already derived from the authenticated user's membership (web requests) or
 *    from a row the caller owns (jobs). Nothing here reads the request.
 *
 * The setting is applied with `set_config(..., false)` — session scope, not
 * transaction scope. That is correct for php-fpm, where a connection serves one
 * request and is then closed. Under PgBouncer in transaction mode, or under
 * Octane, a pooled connection outlives the request and session scope would leak
 * one user's company into the next request on that connection; see the note on
 * `forget()`.
 */
class CompanyDatabaseContext
{
    /**
     * The Postgres session variable the policies read.
     */
    public const SETTING = 'app.company_id';

    /**
     * Point the connection at one company, or at none when given null.
     *
     * Passing null writes an empty string rather than issuing `RESET`: an empty
     * string compares unequal to every company id, so the connection ends up
     * able to see nothing, which is what "no company" has to mean.
     */
    public static function set(?string $companyId, ?string $connection = null): void
    {
        $connection = DB::connection($connection);

        // Only Postgres has the policies. Guarding here keeps the middleware
        // and the jobs free of driver checks, and keeps a sqlite-backed test
        // from failing on an unknown function.
        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement(
            'SELECT set_config(?, ?, false)',
            [self::SETTING, (string) $companyId]
        );
    }

    /**
     * Clear the company from the connection, leaving it able to see nothing.
     *
     * Call this at the end of anything that keeps a connection alive across
     * units of work — a queue worker between jobs, an Octane worker between
     * requests. Under php-fpm it is unnecessary but harmless.
     */
    public static function forget(?string $connection = null): void
    {
        self::set(null, $connection);
    }

    /**
     * The company the connection is currently acting for, or null.
     *
     * Reads the value back out of Postgres rather than a local property, so it
     * reports what the policies will actually see.
     */
    public static function current(?string $connection = null): ?string
    {
        $connection = DB::connection($connection);

        if ($connection->getDriverName() !== 'pgsql') {
            return null;
        }

        $value = $connection->scalar(
            'SELECT current_setting(?, true)',
            [self::SETTING]
        );

        return ($value === null || $value === '') ? null : (string) $value;
    }

    /**
     * Run a callback with the connection pointed at one company, then put the
     * previous company back.
     *
     * The restore runs even when the callback throws, so a failure part way
     * through a loop over companies cannot leave the connection pointed at the
     * wrong books for whatever runs next on it.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function runFor(?string $companyId, callable $callback, ?string $connection = null): mixed
    {
        $previous = self::current($connection);

        self::set($companyId, $connection);

        try {
            return $callback();
        } finally {
            try {
                self::set($previous, $connection);
            } catch (Throwable) {
                // The connection is already broken if this fails; losing the
                // restore must not mask the original exception.
            }
        }
    }
}

-- ---------------------------------------------------------------------------
-- NextBook — create the application database role (step 10, row level security)
-- ---------------------------------------------------------------------------
--
-- Run this ONCE per server, as a superuser, BEFORE deploying the release that
-- contains 2026_10_21_000001_enable_row_level_security.
--
--   psql -h <host> -U postgres -d <database> -v app_password="'<a strong password>'" \
--        -f database/sql/nextbook_app_role.sql
--
-- The password is passed on the command line so it never lives in this
-- repository. Pick a fresh one; it is not the `postgres` password.
--
-- What this role is for
-- ---------------------
-- Postgres does not apply row level security policies to superusers, nor to a
-- table's owner. The application has always connected as `postgres`, so the
-- policies the migration installs would have no effect at all until the
-- application moves to a role that holds neither privilege. That role is
-- `nextbook_app`: it owns nothing, can run no DDL, and therefore cannot switch
-- the policies off even if application code is compromised.
--
-- The schema stays owned by `postgres`, which keeps migrations — including
-- backfills that have to rewrite rows across every company — working exactly
-- as they do today.
--
-- After running this
-- ------------------
--   1. Add to the application's .env:
--
--        DB_CONNECTION=pgsql_app
--        DB_APP_USERNAME=nextbook_app
--        DB_APP_PASSWORD=<the password used below>
--
--      DB_USERNAME / DB_PASSWORD stay as they are: the `pgsql` connection still
--      logs in as the owner and is what migrations use.
--
--   2. Deploy, then run migrations against the owner explicitly:
--
--        php artisan migrate --force --database=pgsql
--
--      Without --database the migration would run as nextbook_app, which has no
--      DDL privileges and cannot install the policies.
--
--   3. php artisan config:clear
--
-- Verifying it took effect
-- ------------------------
--   \c <database> nextbook_app
--   SELECT count(*) FROM sales;                      -- expect 0: no company set
--   SELECT set_config('app.company_id', '<a company ulid>', false);
--   SELECT count(*) FROM sales;                      -- expect that company only
--   ALTER TABLE sales DISABLE ROW LEVEL SECURITY;    -- expect: must be owner
--
-- ---------------------------------------------------------------------------

\set ON_ERROR_STOP on

BEGIN;

-- psql does not substitute :variables inside a dollar-quoted body, so the
-- password reaches the DO block below through a setting local to this
-- transaction instead. Writing :app_password inside the block is a syntax
-- error that stops the whole file before anything is granted. Compared rather
-- than selected so the password is not echoed back to the terminal.
SELECT set_config('nextbook.app_password', :app_password, true) <> '' AS password_received;

-- Created only when missing, and the password of an existing role is never
-- touched: re-running this file must not lock out a working deployment.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'nextbook_app') THEN
        EXECUTE format(
            'CREATE ROLE nextbook_app LOGIN NOSUPERUSER NOBYPASSRLS '
            'NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD %L',
            current_setting('nextbook.app_password')
        );
        RAISE NOTICE 'created role nextbook_app';
    ELSE
        RAISE NOTICE 'role nextbook_app already exists; password left unchanged';
    END IF;
END
$$;

-- Read and write rows; no DDL, no ownership. The migration grants these too,
-- so that a fresh database is usable without this file — they are repeated
-- here because on an existing server the role has to be able to connect before
-- the migration runs.
GRANT USAGE ON SCHEMA public TO nextbook_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO nextbook_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO nextbook_app;

-- Covers tables and sequences created by later migrations, so a new table is
-- reachable without revisiting this file.
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO nextbook_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO nextbook_app;

COMMIT;

-- A sanity check: all three must be false, or the policies will not apply.
SELECT rolname, rolsuper, rolbypassrls, rolcreaterole
FROM pg_roles
WHERE rolname = 'nextbook_app';

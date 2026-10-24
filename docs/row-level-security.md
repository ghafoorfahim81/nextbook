# Row level security — step 10 of the multi-company plan

Written for: whoever deploys this release and whoever extends the policies to
the remaining tables.

Steps 1 to 9 moved the company boundary into the application: a `company_id`
column on every table, a global scope on every model, a `where company_id` on
every hand-written query. That boundary holds only as long as every query
remembers it, and the migration found four separate classes of bug where one
did not — `docs/phase-2-handoff.md`, section 3, lists them. A forgotten filter
is a silent cross-company read, and nothing in PHP catches it.

Step 10 makes Postgres enforce the boundary instead. A query that forgets the
filter now returns too few rows rather than too many: a bug that gets reported
instead of one that gets exploited.

---

## How it fits together

| Piece | Where |
|---|---|
| The policies | `database/migrations/2026_10_21_000001_enable_row_level_security.php` |
| The role, for an existing server | `database/sql/nextbook_app_role.sql` |
| The two connections | `config/database.php` (`pgsql`, `pgsql_app`) |
| Setting the company on a request | `app/Http/Middleware/SetDatabaseCompanyContext.php` |
| Setting it anywhere else | `app/Support/CompanyDatabaseContext.php` |
| Guard for cross-company commands | `app/Console/Concerns/RequiresCrossCompanyAccess.php` |
| Tests | `tests/Feature/Tenancy/RowLevelSecurityTest.php` |

Each policy is one `FOR ALL` rule:

```sql
USING      (company_id = current_setting('app.company_id', true)::char(26))
WITH CHECK (company_id = current_setting('app.company_id', true)::char(26))
```

`USING` decides which rows the connection can see, and therefore which it can
update or delete. `WITH CHECK` decides which rows it may write — without it the
application could insert a row stamped with another company's id, invisible to
itself afterwards but sitting in the other company's books.

### Why there are two database roles

Postgres exempts two kinds of role from a policy: superusers, and the table's
own owner. The application has always connected as `postgres`, a superuser, so
installing policies without also changing who connects would enforce nothing —
and the tests proving isolation would pass against no enforcement at all.

So the schema stays owned by the migrating role, and the application moves to
`nextbook_app`, which owns nothing and holds neither `SUPERUSER` nor
`BYPASSRLS`. The split cuts both ways on purpose:

- The app role **cannot turn the policies off**. `ALTER TABLE ... DISABLE ROW
  LEVEL SECURITY` is an owner privilege, so SQL injection in application code
  cannot lift the boundary that confines it.
- The owner **is not subject to the policies**, so backfill migrations keep
  working. A migration that rewrites rows across every company needs exactly
  that exemption.

This is why the tables use `ENABLE ROW LEVEL SECURITY` and not `FORCE`: `FORCE`
would subject the owner to the policies too and break every cross-company
migration and maintenance query.

### An unset company sees nothing

`current_setting('app.company_id', true)` returns `NULL` when the variable was
never set, and `NULL` compares unequal to every id. So a connection that never
said which company it is acting for sees no rows in the protected tables and
can write none.

That is the decided behaviour, not an accident: a job that forgot its company
gets an empty result set rather than every company's data. The cost is that
anything running outside a request has to say so explicitly — see *Code outside
a request* below.

---

## Scope: every table that carries a company

100 tables, discovered from the schema at migration time rather than listed by
hand. A list has to be remembered: a table added by a later migration would
reach the application with no policy on it, and the only symptom would be the
absence of one. Deriving the set means a new company-scoped table is protected
by virtue of having the column, and a table that must *not* be protected has to
say so in `EXCLUDED_TABLES`, where the reason gets written down. The coverage
test reads the schema the same way, so the two cannot drift.

Two tables are excluded, both because they are read before a company exists:

- **`users`** — read by the authentication guard, before sign-in resolves a
  company at all. Its own `company_id` is nullable for the same reason: a user
  exists before their company does.
- **`branches`** — read by SetActiveBranch, which validates the requested branch
  against the company, and by the branch switcher. Cross-company reads here are
  prevented at the application level by `CompanySpecific` on the Branch model,
  with `tests/Feature/Tenancy/CompanyScopeTest.php` holding it in place.

Two more since phase 5, for the same reason:

- **`user_company_access`** and **`user_branch_access`** — read by
  SetActiveCompany and SetActiveBranch (through `UserAccessService`) to decide
  which company and branch the request acts in, before any company is on the
  connection. They hold membership, not books; only super-admins change them,
  and the composite foreign keys keep a branch grant inside its company.

These two were created by a migration that runs *after* the policy migration,
so the policy migration never saw them — which is the general caveat worth
knowing: **a company-scoped table added by a later migration gets no policy by
itself.** The coverage test in `RowLevelSecurityTest` does see it and fails, so
the gap cannot go unnoticed, but closing it means writing that table's policies
in its own migration (or listing it as exempt, with the reason).

`companies` needs no policy: it has no `company_id`, and `CompanySpecific` on
the model scopes what a request can see.

### Three policy shapes, not one

Most tables get the symmetric rule: read your own, write your own. Two groups
cannot, and the asymmetries run in opposite directions.

**Shared reference data** — `customer_groups`, `payment_terms`. ReferenceDataSeeder
writes these once with `company_id` null: the standard customer groups and
payment terms, the same list for everyone, with the nullable column letting a
company add its own alongside. A plain equality policy hides the entire shared
list from every company. So: **read what is shared or your own, write only your
own.**

That needs four policies rather than one, and `DELETE` is why. A DELETE is
checked against `USING` alone — there is no new row to check — so a single rule
permissive enough to *read* the shared rows would be permissive enough to
*remove* them, letting one company delete the list every other company depends
on. Splitting the rule per command puts `USING` for SELECT and `USING` for
DELETE in different policies.

**The audit trail** — `activity_logs`. ActivityLogService falls back to a null
company when nothing establishes one: a failed sign-in, or anything done before
the user has a company, including the creation of the user. Refusing those
inserts breaks user creation outright. So: **write what you cannot attribute,
read only your own.** An unattributed event can name another company's user, so
it must not become readable by every tenant; those rows stay visible on the
owner connection, which is where an administrator investigating them looks.

---

## Deploying it

On a developer machine nothing needs doing: `DB_CONNECTION` stays `pgsql`, the
migration creates the `nextbook_app` role from `DB_APP_PASSWORD`, and the
application keeps running as the owner. The policies are installed but the
owner is exempt, so behaviour is unchanged.

On a server, in this order:

1. **Create the role**, as a superuser, before deploying:

   ```bash
   psql -h <host> -U postgres -d <database> \
        -v app_password="'<a strong password>'" \
        -f database/sql/nextbook_app_role.sql
   ```

   The password is passed on the command line so it never enters this
   repository. It is not the `postgres` password.

2. **Point the application at it** in `.env`:

   ```
   DB_CONNECTION=pgsql_app
   DB_APP_USERNAME=nextbook_app
   DB_APP_PASSWORD=<the password from step 1>
   ```

   `DB_USERNAME` / `DB_PASSWORD` stay as they are — the `pgsql` connection is
   still the owner, and is what migrations use.

3. **Migrate as the owner, explicitly:**

   ```bash
   php artisan migrate --force --database=pgsql
   php artisan config:clear
   ```

   Without `--database=pgsql` the migration runs as `nextbook_app`, which has no
   DDL privileges and cannot install the policies.

4. **Check it took effect.** As `nextbook_app`:

   ```sql
   SELECT count(*) FROM sales;                    -- 0: no company set yet
   SELECT set_config('app.company_id', '<a company ulid>', false);
   SELECT count(*) FROM sales;                    -- that company only
   ALTER TABLE sales DISABLE ROW LEVEL SECURITY;  -- must fail: must be owner
   ```

### On shared hosting (cPanel)

cPanel gives no superuser, so `database/sql/nextbook_app_role.sql` cannot run
there: it creates a role, and only a superuser (or a role with CREATEROLE) may.
The same two-role arrangement is built from the panel instead.

1. **Create the app user in cPanel → PostgreSQL Databases**, e.g.
   `<account>_nbapp`, with its own strong password, and add it to the
   database. Do not reuse the owner user. cPanel prefixes the name; that is
   fine, because the migration reads the role name from `DB_APP_USERNAME`.
2. **Use an empty database.** Phase 2 edited migrations in place; an
   already-migrated database will not pick those changes up.
3. **Migrate and seed with the owner as the default connection.** Some
   migrations read the cache through the default connection, and the new user
   has no table privileges until the row level security migration grants them:

   ```
   DB_CONNECTION=pgsql
   DB_USERNAME=<owner user>      DB_PASSWORD=<owner password>
   DB_APP_USERNAME=<account>_nbapp   DB_APP_PASSWORD=<its password>
   ```

   ```bash
   php artisan migrate --force --database=pgsql
   php artisan db:seed --force --database=pgsql
   ```

4. **Switch the application to the app user:** `DB_CONNECTION=pgsql_app`, then
   `php artisan config:clear`.
5. **Prove the policies apply to it.** Some hosts make database users members of
   the account's main role; if that role owns the tables, Postgres treats the
   member as the owner and row level security silently does nothing. Connect as
   the app user (`psql -h localhost -U <account>_nbapp -d <database>` — cPanel
   accepts TCP only, not the local socket) and run:

   ```sql
   SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user;          -- f | f
   SELECT pg_has_role(current_user,
          (SELECT tableowner FROM pg_tables WHERE tablename = 'accounts'), 'USAGE');  -- f
   SELECT count(*) FROM accounts;                                                     -- 0
   ```

   The last one must be 0 although the seeded accounts exist: no company is set
   on the connection. Any other answer means the policies are not enforced for
   this user, and the deployment must not go live as it is.

### Rolling back

`php artisan migrate:rollback --database=pgsql` drops the policies and disables
row security; set `DB_CONNECTION` back to `pgsql` and the application is exactly
where it was. The role is left in place — a login role that owns nothing is
harmless, and dropping it would break any other environment still using it.

---

## Code outside a request

A web request is covered: `SetDatabaseCompanyContext` runs straight after
`SetActiveCompany` and copies the resolved company onto the connection. It reads
the same container binding the application itself uses, so the two cannot
disagree, and it *clears* the setting on a request with no company rather than
leaving the previous value in place.

Everything else has to be explicit.

**Queued jobs.** The notification checks already loop one branch at a time, and
a branch belongs to exactly one company, so `NotificationService::inCompanyOf()`
wraps each read in that branch's company. Use the same helper —
`CompanyDatabaseContext::runFor($companyId, fn () => ...)` — in any new job. It
restores the previous company afterwards, including when the callback throws, so
a failure part way through a loop cannot leave the connection pointed at the
wrong books for whatever runs next on that worker.

**Maintenance commands.** `stock:recalculate-averages`,
`transactions:verify-base`, `branches:sync-defaults`,
`settlements:backfill-sale-receives`, `nextbook:seed-demo` and `apply:changes`
sweep every company by design. Run them on the owner connection:

```bash
DB_CONNECTION=pgsql php artisan stock:recalculate-averages
```

They refuse to run otherwise, via `RequiresCrossCompanyAccess`. That guard
exists because the alternative is worse than a crash: under the policies their
queries would match nothing, and they would print "0 rows processed" and exit
successfully, which an operator reads as "nothing was wrong".

**New backfill migrations** need no special handling — migrations run as the
owner, which the policies do not apply to.

---

## The suite runs under the policies

Every test runs as the application role. `Tests\TestCase::setUp()` issues
`SET ROLE nextbook_app` right after `parent::setUp()`, so the policies are in
force for everything a test does.

`SET ROLE` rather than a second connection, for two reasons that each rule the
alternative out on their own:

- `pgsql_app` has no DDL privileges, so `migrate:fresh` would fail on it.
  RefreshDatabase exposes `migrateFreshUsing()` for redirecting that, but every
  test class `use`s the trait itself, and a trait method in the child class
  takes precedence over an inherited one — an override in `TestCase` would
  never be called.
- Switching the connection after `parent::setUp()` would leave the per-test
  transaction on the old one, so fixtures would be written outside it,
  committed, and never rolled back.

With `SET ROLE` the connection, session and transaction are untouched and only
the role the policies are evaluated against changes: the migration and the
transaction belong to the owner, everything the test does afterwards to the
application. The `SET` is inside the transaction, so the rollback resets it.

### The one escape hatch

`TestCase::asSchemaOwner(callable)` runs a callback as the owner and then
returns to the application role. It is for **DDL only** — a test that recreates
a table a later migration drops, or re-adds a check constraint to prove the
migration that changed it works. In production that code path *is* a migration,
which runs as the owner, so this is faithful rather than a way around the
policies.

A test that cannot *write a row* is not hitting a privilege problem. It is
hitting the company boundary, and the fix is to say which company it is acting
for — `CompanyDatabaseContext::runFor()` — not to reach for this.

One trap: `$this->artisan(...)` returns a pending command that executes in its
own destructor, which fires after the callback returns and the role is back. A
command run inside `asSchemaOwner` needs an explicit `->run()`, or it executes
as the role it was being kept away from.

### What turning this on found

Four distinct causes, 44 failures:

- **29 — incoherent fixture data.** `ItemFactory` built its three posting
  accounts with plain `Account::factory()` calls, and `AccountFactory` falls
  back to a `Company::factory()` of its own: every test item pointed at three
  accounts in three different companies, and had done since the factory was
  written. `tools/phase-2/factory_related_company.php` lists the ones that are
  still latent because no test currently leans on the default.
- **11 — tests replaying migration DDL.** Now wrapped in `asSchemaOwner`.
- **3 — tests deliberately building a second company's data.** Kept, with the
  fixture built in that company's own context. These are stricter than before:
  if the code under test went back to using the acting company, the insert is
  now refused outright rather than landing in the wrong books.
- **1 — a cross-company console command invoked in-process**, correctly refused
  by `RequiresCrossCompanyAccess`.

None of them were policy bugs. All of them were things nothing had been able to
notice before.

---

## Things worth knowing before changing any of this

**`ledgers.company_id` is nullable**, and so are `customer_groups`,
`payment_terms` and `activity_logs`. The last three are nullable on purpose and
have their own policy shapes above. `ledgers` is not: no row with a null company
exists in development or in the seeders, and the column should be tightened to
`NOT NULL` before the data grows — a null-company ledger would be invisible to
everyone.

**Constraint checks ignore the policies.** Postgres applies unique and foreign
key checks regardless of row security. That is what keeps foreign keys working
across a policy boundary — but it also means a duplicate against an *invisible*
row still raises a unique violation, which leaks the fact that the other row
exists. It matters for anything user-facing and unique, such as a document
number.

**The cast side matters.** The policy casts the setting, not the column:
`current_setting(...)::char(26)`, never `company_id::text`. `current_setting` is
`STABLE`, so Postgres folds it to a constant and the existing `(company_id, ...)`
indexes still apply. Casting the column instead makes the comparison
unindexable and turns every query on the table into a sequential scan.

**The container binding and the connection have to move together.** The
application reads the acting company from the container — `HasCompany` to stamp
new rows, ActivityLogService to attribute the audit entry — and Postgres
compares that answer against `app.company_id`. Setting one without the other
produces a row whose company disagrees with the connection, which the policies
then refuse. `SetDatabaseCompanyContext` maintains this in production, and
`BuildsErpContext::actingInCompany()` is the same invariant for fixtures.

Note that `HasCompany` prefers the row's own branch over the container, and that
`active_branch_id` therefore counts as part of the context. Leaving a previous
company's branch bound while building another company's fixtures stamps the new
rows with the old company — which is what `actingInCompany()` clears it for.

**Run the factory scanner before extending anything.**

```bash
php tools/phase-2/factory_related_company.php --all
```

It lists every factory default that builds a related company-scoped row without
saying which company it belongs to. Both its lists are at zero now; anything
that appears is a latent failure waiting for the first test that leans on the
default. The fix is the one applied to `ItemFactory`: declare `company_id` — or
the branch it is derived from — before the related key, since Laravel expands a
definition in order and hands each closure what has already resolved.

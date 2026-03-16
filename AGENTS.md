# AGENTS.md

## Cursor Cloud specific instructions

### Overview

This is a Laravel 12 + Vue 3 (Inertia.js) multi-tenant business/accounting ERP application. It manages inventory, purchases, sales, accounting/ledger, expenses, and user roles/permissions. The frontend uses shadcn-vue with Tailwind CSS.

### Required System Dependencies

- **PHP 8.2+** with extensions: pgsql, mbstring, xml, zip, gd, bcmath, intl, curl
- **Composer** (PHP package manager)
- **PG 16** (primary data store; see `.env` for connection config)
- **Node.js 22+** and **npm** (frontend tooling)

### DB Setup

PG must be running: `sudo pg_ctlcluster 16 main start`

The VM uses trust auth on `pg_hba.conf` for local connections. See `.env` for host, port, and credential values.

After fresh cloning, run: `php artisan migrate:fresh --seed --force`

The default admin user is seeded by the main seeder class (email from `UserSeeder`, password: `password`). After seeding, the admin user must be associated with a company for login to work:
```
php artisan tinker --execute="\$u = \App\Models\User::where('name','admin')->first(); \$c = \App\Models\Administration\Company::first(); \$u->company_id = \$c->id; \$u->save(); \$u->assignRole('admin');"
```

### Running the Application

The full dev stack is started via `composer dev` (uses `concurrently` to run Laravel server, queue worker, log tail, and Vite). Alternatively, start individually:
- `php artisan serve --host=0.0.0.0 --port=8000` (backend)
- `npm run dev` (Vite HMR on port 5173)

### Testing

Tests use **Pest** (PHP): `./vendor/bin/pest`

Feature tests use `RefreshDb` trait and require a working PG connection. The `phpunit.xml` uses the default pgsql connection (SQLite lines are commented out). Many existing tests have pre-existing failures unrelated to environment setup.

The `jasonmccreary/laravel-test-assertions` dev dependency is required for Blueprint-generated controller tests.

### Build

- Frontend build: `npm run build` (Vite production build)
- No dedicated linter is configured (no ESLint, no Laravel Pint config). The Vite build serves as the frontend compilation check.

### Gotchas

- The `HandleInertiaRequests` middleware accesses `$request->user()->company->calendar_type`. If the user has no company association, this crashes. A null-safe fix (`?->` with `?? 'AD'` fallback) was applied.
- When regenerating `node_modules`, you may need to delete both `node_modules` and `package-lock.json` to resolve `@rollup/rollup-linux-x64-gnu` missing module errors, then run `npm install`.
- Use `php artisan config:clear` if you see stale config behavior after `.env` changes.
- The codebase has several PSR-4 autoloading mismatches (e.g., `LedgerOpeningControllerTest`, `DesignationSeeder`) that produce warnings but don't affect runtime.

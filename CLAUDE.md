# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**NextBook** is a Laravel 12 + Vue 3 (Inertia.js) multi-tenant ERP application covering inventory, purchasing, sales, accounting (double-entry), expenses, and user roles/permissions. It targets Afghan businesses and supports both Gregorian and Jalali (Persian) calendars.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2+, PostgreSQL 16
- **Frontend:** Vue 3, Inertia.js, shadcn-vue, Tailwind CSS 3.4
- **Build:** Vite 5 with `laravel-vite-plugin`
- **Auth:** Laravel Jetstream + Sanctum + Spatie Laravel Permissions
- **PDF:** mPDF | **Routing in JS:** Ziggy | **Calendar:** morilog/jalali
- **Testing:** Pest 3 (PHPUnit runtime) against a real PostgreSQL database

## Commands

### Development

```bash
composer dev          # Recommended: runs server + queue + logs + Vite concurrently
```

Or separately:

```bash
php artisan serve --host=0.0.0.0 --port=8000
npm run dev
```

### Database

```bash
php artisan migrate:fresh --seed --force   # Reset and re-seed
php artisan tinker                          # Interactive shell
```

### Build

```bash
npm run build
php artisan optimize:clear
```

### Testing

```bash
php artisan test                              # All tests
php artisan test --filter="test_name"        # Single test
php artisan test --testsuite=Feature         # By suite
php artisan test tests/Feature/SomeTest.php  # Specific file
./vendor/bin/pest                             # Direct Pest invocation
```

Tests require a `.env.testing` file pointing to a `nextbook_test` PostgreSQL database. Tests do **not** use SQLite.

## Architecture

### Request Lifecycle (Inertia.js)

1. Request hits a Laravel web route → Controller returns `Inertia::render('PageName', $props)`
2. `HandleInertiaRequests` middleware (app/Http/Middleware/) shares global props (auth user, company, flash messages) with every page
3. Vue 3 page component in `resources/js/Pages/` receives props and renders using `resources/js/Layouts/Layout.vue`
4. Navigation and data mutations go through standard form submissions / Inertia `useForm` — no separate REST API for the frontend

`HandleInertiaRequests` accesses `user->company->calendar_type`, so a user **must** have an associated company or the middleware will error.

### Multi-Tenancy

- Every authenticated request is scoped to the user's **branch** and **company**
- `CheckCompany` middleware enforces that a company exists before routing to protected pages
- Models are scoped by `branch_id` and `company_id` at the controller level (not global scopes), so always include these when querying or creating records

### Database Conventions

- **Primary keys:** ULID (`char(26)`) — not auto-increment integers
- **Soft deletes:** Enabled on most entities; trashed records are recoverable via `/deleted-records`
- **Audit columns:** `created_by`, `updated_by`, `deleted_by` (user IDs) on almost every table
- **Currency fields:** Transactions store both home-currency and foreign-currency amounts with an exchange rate column
- Models are organized by domain under `app/Models/`: `Account/`, `Accounting/`, `Inventory/`, `Ledger/`, `Purchase/`, `Sale/`, `Transaction/`, `Administration/`

### Accounting Engine

- Full **double-entry** bookkeeping: every financial event posts to `transaction_lines` (debit/credit)
- `journal_entries` / `journal_entry_lines` for manual GL entries
- `ledger_openings` for beginning balances on supplier/customer sub-ledgers
- `account_balance_snapshots` used for period closing and reporting performance
- FIFO stock costing tracked in `stock_balances`

### Permissions

Spatie roles and permissions are used throughout. Controllers check permissions via `$this->authorize()` or `Gate::allows()`. Role/permission definitions live in database seeders.

### Frontend Structure

```
resources/js/
├── app.js              # Inertia bootstrap, i18n, global component registration
├── Pages/              # One Vue file per Inertia page (maps to controller return)
├── Layouts/
│   └── Layout.vue      # Main authenticated layout (sidebar, topbar)
├── Components/         # Shared UI components (shadcn-vue wrappers + custom)
├── composables/        # Vue composables (usePermission, useCurrency, etc.)
├── lib/                # i18n setup, theme helpers
└── utils/              # Formatting utilities
```

- Path alias `@/*` → `resources/js/*` (configured in `jsconfig.json` and `vite.config.js`)
- Component library: **shadcn-vue** (`components.json`, style: `new-york`, icons: `lucide`)
- Custom color palette defined in `tailwind.config.js` and `COLOR_SYSTEM.md` (Nextbook purple `#8b5cf6`, yellow `#f59e0b`, blue-gray)
- Dark mode via `dark` class on `<html>`

### Key Middleware Chain (web routes)

`auth:sanctum` → `verified` → `CheckCompany` → controller

### Routes Organization (`routes/web.php`)

Routes are grouped by domain: Administration, Inventory, Accounting (ledgers, suppliers, customers, transfers), Transactions (purchases, sales, journal entries, payments, receipts), Reports, Activity Logs, Notifications, Deleted Records. All protected routes require the middleware chain above.

### Testing Patterns

- Uses `BuildsErpContext` trait to bootstrap a company, branch, currency, and admin user for each test
- Feature tests live under `tests/Feature/` organized by module
- Factories in `database/factories/` (13+ domain directories) for seeded test data
- Financial and stock integrity (FIFO) are covered by integration tests in `tests/Integration/`

## Key Docs in Repo

| File | Purpose |
|---|---|
| `AGENTS.md` | System requirements and environment setup steps |
| `TESTING_GUIDE.md` | Full testing strategy and conventions |
| `COLOR_SYSTEM.md` | Brand color palette and Tailwind class reference |
| `docs/controllers.md` | Controller/endpoint reference |
| `docs/models.md` | Eloquent model reference |
| `docs/routes.md` | Route listing and conventions |
| `docs/enums.md` | PHP enum definitions |
| `docs/activity-logging.md` | Audit trail implementation details |
| `docs/frontend-components.md` | Vue component library reference |
| `docs/frontend-composables.md` | Reusable Vue composable reference |

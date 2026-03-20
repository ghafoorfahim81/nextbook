# TESTING_GUIDE.md

## 1) Overview

This ERP test suite is implemented with Laravel testing (PHPUnit + Pest runtime) and PostgreSQL.

It includes:
- Feature tests (HTTP -> Controller -> Service -> DB)
- Unit tests (services/helpers/calculations)
- Integration tests (cross-module accounting/inventory/reporting flows)
- Factory coverage for core ERP entities

Core focus areas:
- Financial calculation correctness
- Stock accuracy (FIFO/average behavior in current implementation)
- Transaction and ledger integrity

---

## 2) How To Run Tests

Run all tests:

```bash
php artisan test
```

Run a specific file:

```bash
php artisan test tests/Feature/Purchase/PurchaseFeatureTest.php
```

Run by filter (single test/method):

```bash
php artisan test --filter="test_purchase_creation_inserts_items_posts_transaction_and_updates_stock"
```

Run test suites:

```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
```

---

## 3) Test Structure

```text
tests/
├── Feature/
│   ├── Accounting/
│   ├── Inventory/
│   ├── Purchase/
│   ├── Sales/
│   ├── Reports/
│   └── Notifications/
├── Unit/
│   ├── Services/
│   └── Calculations/
├── Integration/
├── Support/
│   └── BuildsErpContext.php
├── CreatesApplication.php
├── Pest.php
└── TestCase.php
```

Key support:
- `tests/Support/BuildsErpContext.php`: creates branch/user/company/currency/accounts/ledgers/item/cache test context.

---

## 4) Factory Coverage

Factories used/added for testing:
- `UserFactory`
- `LedgerFactory`
- `TransactionFactory`
- `TransactionLineFactory` (added)
- `ItemFactory`
- `StockBalanceFactory` (added)
- `PurchaseFactory`
- `SaleFactory` (added)
- `SaleItemFactory` (added)
- Supporting admin/account factories (branch, company, currency, account type/account, warehouse, unit measure, quantity, size, etc.)

---

## 5) Database Setup For Testing

Tests use the configured PostgreSQL connection (not in-memory SQLite in this project).

Recommended `.env.testing` example:

```env
APP_ENV=testing
APP_DEBUG=true
APP_KEY=base64:your_testing_key_here
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nextbook_test
DB_USERNAME=postgres
DB_PASSWORD=
CACHE_STORE=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
MAIL_MAILER=array
```

Then run:

```bash
php artisan config:clear
php artisan test
```

`RefreshDatabase` is used in the suite to isolate test state.

---

## 6) How To Add New Tests

1. Pick the correct layer:
- Feature: full HTTP flow
- Unit: isolated business logic
- Integration: interaction across modules

2. Reuse ERP context bootstrap:
- `use BuildsErpContext;`
- `use RefreshDatabase;`
- Initialize context in `setUp()`

3. Keep data minimal but realistic:
- One branch/company/user context
- One item/ledger/account set unless scenario needs more

4. Use strong assertions:
- `assertDatabaseHas`
- `assertDatabaseMissing`
- `assertEquals`
- `assertTrue`
- `assertSoftDeleted`

5. Validate math explicitly:
- Debit/credit totals
- Ledger balances (Dr/Cr)
- Stock quantities/cost values

---

## 7) Common Issues And Fixes

1. `null value violates not-null constraint`
- Usually missing required FK in payload (example: `bank_account_id` in purchase flow).
- Ensure test payload includes required DB-level fields, not only request-level optional fields.

2. Report tests return zero unexpectedly
- Trial balance in this codebase is ledger-line based; include `ledger_id` in relevant transaction lines.
- Verify date conversion/calendar behavior and date range.

3. Inertia response assertions fail with `409`
- Prefer `assertInertia(...)` on normal GET response instead of forcing raw Inertia headers without version handling.

4. Stock mismatch assertions
- Confirm expected math aligns with current service logic (FIFO path vs non-FIFO branch behavior).

5. Existing legacy generated tests
- Some old blueprint-generated tests may not reflect the current schema. Run targeted files while incrementally modernizing legacy tests.

---

## 8) Notes On Optional Frontend Testing

Frontend unit/component testing is optional and not fully wired in this repository yet.
Recommended path:
- Add `vitest` + `@vue/test-utils`
- Start with key Vue components used in accounting/inventory entry forms
- Keep API contract assertions covered at Laravel feature/integration level

---

## 9) Current Implemented Coverage Highlights

- Accounting:
  - Journal entry posting
  - Receipt/payment logic
  - Customer/supplier balance effects
  - Account transfer correctness
- Inventory:
  - IN/OUT stock movement
  - FIFO and non-FIFO cost path behavior
  - Batch requirement validation
  - Expiry/batch-aware stock balance checks
  - Non-negative stock protection
- Purchase/Sales:
  - Creation flows with item lines
  - Stock updates
  - Transaction posting and balance impacts
  - Soft delete behavior checks
- Reports:
  - Trial balance
  - Balance sheet equation consistency
  - Income statement net profit checks
  - Low stock and inventory valuation
- Preferences/Notifications:
  - Notification preference persistence
  - Idempotent repeated preference updates

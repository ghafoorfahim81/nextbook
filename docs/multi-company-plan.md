# Multi-Company Plan

Status: **approved, not started**
Decided: 2026-10-03
Branch at time of writing: `refactor-items-and-company-wise`

Farsi version: [multi-company-plan-fa.md](multi-company-plan-fa.md)

How NextBook will support one customer running several businesses — each with its
own business type, settings, books and data.

---

## 1. The problem, stated accurately

The request started as "we have branches, we need companies". The code says
otherwise. **A branch in this codebase already is a company.**

[`BranchProvisioningService`](../app/Services/BranchProvisioningService.php) gives
every branch its own:

- full chart of accounts and account types
- currency set, with its own `is_base_currency`
- financial period
- item catalogue, categories, brands, unit measures, quantities, sizes
- warehouse, cash-customer ledger, expense categories, journal classes
- HR config — shifts, leave types, salary components, Afghan wage-tax brackets
- invoice number sequence

That is not a branch. That is a company — roughly 200 seeded rows per branch,
isolated by the `branchSpecific` / `branch` global scopes.

Meanwhile the existing `companies` table is **not** a tenant. It is a settings
and profile row attached to the user: `business_type`, `preferences`,
`calendar_type`, `locale`, `currency_id`, `costing_method`, logo, invoice theme.

So the plan is not to build a company concept. It is to **stop lying about the
one that already exists**, and to give it the attributes a company needs.

### The security hole this leaves today

`branches` has no owner column. [`SwitchBranchController`](../app/Http/Controllers/Administration/SwitchBranchController.php)
validates `exists:branches,id` with no scoping, so a super-admin can set
`branch_id` to **any branch in the database** and every global scope then serves
them that tenant's data. Closing this is phase 2's main deliverable.

---

## 2. The decision

**Rename `branches` → `companies`. One level of tenancy. Database per customer.**

```
Database  = one customer (even with 100 companies)
Company   = one business: own business type, settings, books, catalogue, licence,
            fiscal year, currency, chart of accounts
  └─ parent_id  = optional chain parent (column already exists on branches)
Warehouse = a stock location inside a company
```

### Deployment model

**One database per customer**, permanently — including after any move to SaaS.
The database *is* the tenant boundary. Consequences:

- no tenant anchor row is needed; no group/holding node
- `company_id IS NULL` is safe and means "shared across this customer's companies"
- every deploy must migrate every database (see §8)

### Why not a two-level model (company > branch)

A two-level model was designed and rejected. It required deduplicating each
branch's chart of accounts into one company-level chart, which means rewriting
`transaction_lines.account_id` across posted double-entry data. That is the one
migration in this system that can silently move a trial balance. The rename
achieves the correct naming with **no data movement at all**, and keeps the
two-level option open through `parent_id`.

### What the single level gives up

Honestly, three everyday operations, and only for a *chain* — one owner with
several same-type shops:

1. **Catalogue per shop.** A new product is entered once per company.
2. **Customer with two balances.** Someone buying on credit at two shops has two
   ledgers. Credit limits and AR aging are per company.
3. **Stock transfer between shops.** [`item_transfers`](../database/migrations/2026_01_14_094905_create_item_transfers_table.php)
   is warehouse→warehouse *within one* `branch_id`. Moving stock between shops
   becomes an inter-company transaction, not a transfer.

None of these matter for genuinely different businesses (a supermarket and a
pharmacy share nothing useful). If chains become a real segment, `parent_id`
plus an ancestor-chain scope addresses all three, and the scope lives in one
function.

### The company boundary rule

> If the catalogue, customers and books are shared → **one company**.
> If they are not → **separate companies**.

`business_type` is the practical proxy: same type usually means shared
catalogue. Note that **licence number is not the criterion** — in Afghanistan
every shop location needs its own licence even within one business, so licence
lives on the company row and a chain simply has several companies each with its
own licence.

---

## 3. Target data ownership

| Level | Mechanism | Holds |
|---|---|---|
| Definitional / shared | `company_id IS NULL` | quantities, unit measures, sizes, account types, brands, HR config (shifts, leave types, salary components, tax brackets) |
| Company | `company_id = X` | items and variants, chart of accounts, currencies and rates, customers and suppliers, financial periods, settings, licence |
| Location | `warehouse_id` | stock, lots, pieces |

**Accounts and currencies stay per company.** This is deliberate and it reverses
an earlier draft. Each company is a separate set of books, so a separate chart
of accounts is correct accounting — QuickBooks and Tally both work this way —
and sharing them would require exactly the risky `transaction_lines` rewrite
this plan exists to avoid.

**Settings duplication is solved by inheritance, not by NULL.** See phase 6.

---

## 4. What stays as it is

Worth stating so nobody "fixes" these:

- `business_type` stays an **enum plus config**, never a table.
  [`config/business_profiles.php`](../config/business_profiles.php) already says
  it: *"Adding a new trade means adding a key here. It must never mean a
  migration."* This profile engine — which pre-shapes the item form per trade —
  is NextBook's actual differentiator; neither Odoo nor Tally does it.
- Afghan payroll wage tax stays seeded data with an `effective_from` date, so a
  rate change is an edit and historical periods still reproduce.
- No sales-tax or tax-registration fields. There is no tax registration in the
  Afghan market yet. Tax stays a company setting defaulting to zero, so it is a
  toggle if that changes.
- No group/consolidation layer. Without a shared tax registration nobody needs a
  combined legal statement. A cross-company owner dashboard is a report, not a
  schema change.

---

## 5. Phases

Each phase ships independently. Only phase 2 is large; nothing moves financial
data.

### Phase 0 — audit and existing bugs (no risk)

1. Artisan command `company:audit` reporting: company count, branch count, and
   any branch holding users from more than one company. **If any branch is
   shared across companies, stop** — that needs a manual decision.
2. Fix three bugs that already exist:
   - [`Company`](../app/Models/Administration/Company.php) has no `SoftDeletes`
     and `companies` has no `deleted_at`, but [routes/web.php:123](../routes/web.php)
     exposes `companies.restore ... withTrashed()`.
   - [`Administration/CompanyController::index`](../app/Http/Controllers/Administration/CompanyController.php)
     calls `Company::search()`; the model does not `use HasSearch`.
   - `Account` and `Currency` declare `tenant_id` in `$fillable` and `casts()`,
     and `CurrencyResource` returns it, but no migration creates the column.
     Dead — remove it.
3. Rename `--tenant=` to `--company=` in
   [`SeedDemoData`](../app/Console/Commands/SeedDemoData.php). After this plan
   "tenant" means database, and the option actually takes a company id.

### Phase 1 — company attributes onto `branches` (low risk, additive)

Add to `branches`, all nullable, nothing dropped:

```
business_type, calendar_type, locale, currency_id, costing_method,
preferences (json), logo, abbreviation, name_fa, name_pa,
email, website, address, phone, country, city,
invoice_description, invoice_theme,
licence: license_number, license_issued_at, license_expires_at,
fiscal year: fiscal_year_start_month, fiscal_year_start_day,
timezone, is_active, deleted_by
```

Backfill each branch from the old `companies` row reached through that branch's
users. Add `unique(['license_number', 'deleted_at'])`.

Licence notes: it belongs to the company (= shop), and printed documents should
carry the **issuing company's** licence. `license_expires_at` can drive a
renewal reminder through the existing `document_expiry_alert` notification
preference.

### Phase 2 — the rename (medium risk, mechanical)

Order matters — the name `companies` must be freed first.

1. `companies` → `legacy_company_settings` (keep for one release, then drop)
2. `branches` → `companies`
3. `branch_id` → `company_id` on all ~96 tables, indexes and FKs renamed with it
4. collapse `users.branch_id` + `users.company_id` into `users.company_id`
5. `users` unique keys: `['company_id','email','deleted_at']` and
   `['company_id','username','deleted_at']`
6. **Scope the company switcher to companies the user is a member of.** This is
   where the security hole closes.

Code rename map:

| From | To |
|---|---|
| `BranchSpecific` + `BelongsToBranch` (two traits, same job) | one `BelongsToCompany` |
| `BranchContext` | `PostingContext` |
| `SetActiveBranch` | `SetActiveCompany` |
| `active_branch_id` | `active_company_id` |
| `BranchScopedUnique` | `CompanyScopedUnique` |
| `SwitchBranchController` | `SwitchCompanyController` |
| `BasePolicy::sameBranch()` | `sameCompany()` |
| `BranchProvisioningService` | `CompanyProvisioningService` |

Surface measured 2026-10-03:

- 1327 occurrences of `branch_id` across 382 PHP files
- 31 occurrences across 12 JS/Vue files — the frontend is barely affected
- 103 test files reference branches — these are the safety net
- 40 translation files mention branch (en / fa / ps)

Two simplifications fall out: the one remaining trait replaces two
inconsistently-applied ones, and `PostingContext` loses its per-branch
memoisation of ~40 GL account slugs, since slug lookup is now unambiguous per
company.

One behaviour to fix while renaming: [`BranchSpecific`](../app/Traits/BranchSpecific.php)
bails out when no branch is bound, so console commands and queue workers see
every tenant's rows. Replace with an explicit `Tenancy::runFor($companyId, fn () => …)`
that jobs must call, and fail loudly otherwise. Audit every `dispatch()` for a
serialised company id.

### Phase 3 — multi-company users (low risk)

```
company_user: company_id, user_id, is_default, joined_at
              PRIMARY KEY (company_id, user_id)
```

`users.company_id` stays as "last active company". Company switcher in the
topbar, visible only when the pivot has more than one row. The switcher must
validate membership, put the company in the session, and flush caches — replace
the hand-written list of ~25 `cache()->forget()` calls in the current switch
controller with a tagged flush.

Plus an "all businesses" dashboard: `whereIn('company_id', $memberCompanyIds)`.
Not a consolidation layer, just a report.

### Phase 4 — company creation wizard (low risk)

Four steps: identity and licence → business type → currency, calendar, fiscal
year → first warehouse.

Business type step shows a live preview of what that trade turns on, read from
`config/business_profiles.php`. Duplicate licence number warns: they probably
meant to add a warehouse to an existing company.

On submit, one transaction: company → `company_user` row →
`CompanyProvisioningService->provision()` seeded by the profile.

Provisioning splits in three:

| Service | Runs | Creates |
|---|---|---|
| `SystemProvisioningService` | once per database | the NULL-shared definitional sets (phase 5) |
| `CompanyProvisioningService` | per company | chart of accounts, account types, currencies, financial period, cash customer, expense categories, journal classes |
| warehouse provisioning | per warehouse | nothing yet; main warehouse row |

### Phase 5 — NULL-share the definitional sets (low risk)

Make `company_id` nullable and dedupe to one shared row per database for:
`quantities`, `unit_measures`, `sizes`, `account_types`, `brands`, and HR config
(`shifts`, `leave_types`, `salary_components`, `tax_bracket_sets` + brackets).

These are safe because their foreign keys are few and non-financial. The scope
becomes:

```php
$q->where('company_id', $activeCompanyId)->orWhereNull('company_id');
```

A nullable `company_id` is then a schema-level statement that a table holds
shareable master data. Transactional tables stay `NOT NULL`.

Explicitly **not** in this phase: accounts and currencies (§3).

### Phase 6 — settings resolver (low risk)

Four layers:

```
config/preferences.php            defaults
  → business profile              per business_type
    → parent company (parent_id)  chain-level policy
      → company                   this business
        → user                    personal only
```

Steps:

1. Move `User::DEFAULT_PREFERENCES` ([User.php:54-469](../app/Models/User.php), 415 lines)
   into `config/preferences.php`, split into explicit `company_scoped` and
   `user_scoped` key lists. Company-scoped: invoice prefixes, tax, posting
   rules, confirmations, item fields, security, backup. User-scoped: appearance,
   theme, font sizes, records per page, sounds, sidebar, onboarding.
2. New `App\Support\Settings` resolver, memoised per request.
   `BusinessProfile` becomes a layer inside it, not a parallel system.
3. Rewire [`PreferencesController`](../app/Http/Controllers/Preferences/PreferencesController.php),
   which currently reads and writes `users.preferences` only. Writing a
   company-scoped key needs `company.settings.update`.
4. Rewire [`CoreShared`](../app/Support/Inertia/CoreShared.php) so
   `user_preferences` is the resolved merge.

The **parent company layer is what solves settings duplication**: a customer who
had one company with three branches ends up with three companies, each with a
copy of `preferences`. Set the parent once and all three inherit. This is the
first place `parent_id` earns its keep.

### Phase 7 — modules and roles (medium risk)

1. Add a `modules` group to each business profile — `['hr' => true, 'pos' =>
   false, 'manufacturing' => true, …]` — and feed it into both the sidebar and
   an `EnsureModuleEnabled` middleware, so a pharmacy cannot reach
   `/manufacturing` by URL. Business type currently drives only item fields.
2. Enable Spatie teams with `team_foreign_key = company_id`. Today
   [`config/permission.php`](../config/permission.php) has `'teams' => false`, so
   a role created in one company is assignable in another. Permissions stay
   global (they are code-defined strings); roles become per company. The pivot
   primary keys gain `company_id`, so this is a table rebuild: create, copy,
   swap. The stock migration types the team key as `unsignedBigInteger` — write
   your own for ULIDs.
3. Three tiers: `platform-admin` as a user flag for cross-company support
   access (today `super-admin` is matched by string in `SetActiveBranch` and the
   switch controller); `company-admin` per company; operational roles below.
   Seed a default role set per company at creation.

---

## 6. Open questions

1. **Are there customers with several same-type shops?** If yes, the `parent_id`
   settings inheritance from phase 6 is needed from the start, not at phase 6.
2. **Invoice numbering across a chain.** Per-company sequences are the default.
   If a chain wants one sequence with per-shop prefixes (`INV-KBL-`,
   `INV-HRT-`), [`HasSequentialNumber`](../app/Models/Concerns/HasSequentialNumber.php)
   needs a composite key.
3. **Exchange rates.** `currency_rate_updates.branch_id` becomes
   `company_id`, so each company keeps its own rates. For a chain that means
   entering today's USD rate once per shop. Candidate for parent inheritance.

---

## 7. Where this came from

Five systems were compared before deciding. All of them agree on the shape:

| System | Books level | Location level |
|---|---|---|
| QuickBooks | company file (own subscription) | Location / Class tag |
| Xero | organisation | tracking category |
| Odoo | `res.company` (one DB, `parent_id` tree) | warehouse / analytic account |
| SAP | company code (BUKRS) | plant (WERKS) → storage location |
| NetSuite | subsidiary | location |
| Tally | company data file | cost centre / godown |

Two conclusions drove the design:

- **No system keeps master data below the books level.** NextBook does today —
  which is what the rename fixes by making the books level and the data level
  the same thing.
- **No system calls a location a company.** Hence the boundary rule in §2.

Borrowed deliberately: Odoo's `company_id IS NULL` sharing and its
multiple-active-companies switcher; Tally's keyboard-first data entry speed and
zero-config start, which is the actual reason for its loyalty in this market and
the right model for the POS module ([`PosSession`](../app/Models/POS/PosSession.php)
is currently a model with no controller or page). Deliberately not borrowed:
Tally's file-per-company data model, and Odoo's configuration depth.

---

## 8. Operational work this implies

One database per customer is not free. Before the tenth customer:

1. A `migrate` command that runs across every customer database and reports
   which failed. Without it schema versions diverge silently.
2. A central registry of customers and their connections, or a config file per
   install.
3. Per-database backup and restore runbook.

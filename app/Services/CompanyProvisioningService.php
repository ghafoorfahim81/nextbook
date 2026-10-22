<?php

namespace App\Services;

use App\Enums\FinancialPeriodStatus;
use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Accounting\FinancialPeriod;
use App\Models\Administration\Brand;
use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Administration\Category;
use App\Models\Administration\Currency;
use App\Models\Administration\Department;
use App\Models\Administration\Quantity;
use App\Models\Administration\Size;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\Expense\ExpenseCategory;
use App\Enums\TaxPeriod;
use App\Models\Hr\LeaveType;
use App\Models\Hr\SalaryComponent;
use App\Models\Hr\Shift;
use App\Models\Hr\TaxBracket;
use App\Models\Hr\TaxBracketSet;
use App\Models\JournalEntry\JournalClass;
use App\Models\Ledger\Ledger;
use App\Models\User;
use App\Support\CompanyContext;
use App\Support\CompanyDatabaseContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;
use Symfony\Component\Uid\Ulid;

/**
 * Creates the default data a company needs in order to trade.
 *
 * Chart of accounts, currencies, catalogue, customers and HR configuration are
 * company-level and shared by every branch under it — that sharing is the whole
 * point of the two-level model, and is what lets stock move between branches as
 * a transfer rather than a sale. Only the warehouse is provisioned per branch;
 * see BranchProvisioningService.
 *
 * Two properties matter here:
 *
 *  - It is atomic. Roughly 200 inserts either all land or none do, so a failure
 *    part-way through can no longer leave a company with accounts but no currencies.
 *  - It is idempotent. Every set is matched on its natural key and only what is
 *    missing gets written, so the same method backfills companies that were created
 *    before a default was added to the codebase.
 */
class CompanyProvisioningService
{
    /**
     * Provision (or backfill) every default set for a company.
     *
     * @return array<string, int> rows created per module
     */
    public function provision(Company $company, ?string $actorId = null): array
    {
        $actorId = $actorId ?? Auth::id() ?? $this->fallbackActorId();

        // The company being provisioned is not the caller's active company: on
        // first sign-up the user has none yet, and `branches:sync-defaults`
        // backfills companies the operator does not belong to. Two of the sets
        // written below — accounts and the cash customer ledger — are under row
        // level security, and the policies' WITH CHECK rejects a row stamped
        // with any company but the one on the connection. So the connection is
        // pointed at the new company for the duration, and put back afterwards.
        $created = CompanyDatabaseContext::runFor($company->id, fn (): array => DB::transaction(function () use ($company, $actorId): array {
            // Account types first — accounts resolve their type by slug within
            // this company, and the default account list reads account types too.
            $counts = ['account_types' => $this->accountTypes($company, $actorId)];

            $counts['accounts'] = $this->accounts($company, $actorId);
            $counts['currencies'] = $this->currencies($company, $actorId);
            $counts['quantities'] = $this->quantities($company, $actorId);
            $counts['unit_measures'] = $this->unitMeasures($company, $actorId);
            $counts['sizes'] = $this->sizes($company, $actorId);
            $counts['ledgers'] = $this->cashCustomer($company, $actorId);
            $counts['expense_categories'] = $this->expenseCategories($company, $actorId);
            $counts['journal_classes'] = $this->journalClasses($company, $actorId);
            $counts['categories'] = $this->categories($company, $actorId);
            $counts['brands'] = $this->brands($company, $actorId);
            $counts['departments'] = $this->departments($company, $actorId);
            $counts['shifts'] = $this->shifts($company, $actorId);
            $counts['leave_types'] = $this->leaveTypes($company, $actorId);
            $counts['salary_components'] = $this->salaryComponents($company, $actorId);
            $counts['tax_brackets'] = $this->taxBrackets($company, $actorId);
            $counts['financial_periods'] = $this->financialPeriod($company, $actorId);

            return $counts;
        }));

        // Posting accounts and the base currency may have just changed.
        CompanyContext::flush($company->id);

        return $created;
    }

    /**
     * Existing values of a column for one company, ignoring the global scope so this
     * works from a console command as well as from a request.
     *
     * Soft-deleted rows count as present: a default the user deleted on purpose
     * should stay deleted, not come back as a duplicate on the next sync.
     *
     * @return array<int, string>
     */
    private function existing(string $modelClass, Company $company, string $column): array
    {
        return $modelClass::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->pluck($column)
            ->filter()
            ->all();
    }

    /**
     * Insert a row with events muted.
     *
     * Muting events keeps provisioning from writing ~200 activity-log entries and
     * cache flushes, but it also disables the ULID and audit-column hooks — so id,
     * company_id and created_by are always set explicitly here.
     */
    private function insert(string $modelClass, array $attributes): Model
    {
        return $modelClass::withoutEvents(function () use ($modelClass, $attributes): Model {
            $model = new $modelClass();

            $model->forceFill([
                'id' => (string) new Ulid(),
                ...$attributes,
            ])->save();

            return $model;
        });
    }

    /**
     * Fill columns that are still null on a row the defaults own.
     *
     * Only nulls are written — a value the user has edited is never overwritten.
     * Runs as a query-builder update so it does not fire model events.
     */
    private function fillNulls(string $modelClass, Model $current, array $values): void
    {
        $updates = [];

        foreach ($values as $column => $value) {
            if ($value !== null && $current->{$column} === null) {
                $updates[$column] = $value;
            }
        }

        if ($updates === []) {
            return;
        }

        $modelClass::withoutGlobalScopes()
            ->where('id', $current->id)
            ->update($updates);
    }

    private function accountTypes(Company $company, ?string $actorId): int
    {
        $existing = AccountType::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->get(['id', 'slug', 'nature', 'remark'])
            ->keyBy('slug');

        $created = 0;

        foreach (AccountType::defaultAccountTypes() as $type) {
            $current = $existing->get($type['slug']);

            if ($current) {
                // Account types written by the old inline provisioning have no
                // nature, and transfers read it to decide debit/credit direction —
                // so fill it in where it is missing, without touching edited values.
                $this->fillNulls(AccountType::class, $current, [
                    'nature' => $type['nature'] ?? null,
                    'remark' => $type['remark'] ?? null,
                ]);

                continue;
            }

            $this->insert(AccountType::class, [
                'name' => $type['name'],
                'slug' => $type['slug'],
                'nature' => $type['nature'] ?? null,
                'remark' => $type['remark'] ?? null,
                'is_main' => $type['is_main'],
                'company_id' => $company->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function accounts(Company $company, ?string $actorId): int
    {
        $typeIds = AccountType::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->pluck('id', 'slug');

        // Slug => account for everything this company already has, so entries that
        // declare a parent_slug link to the right parent whether it was created
        // just now or on an earlier run.
        $existing = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->get(['id', 'slug', 'local_name', 'parent_id'])
            ->keyBy('slug');

        $accountIds = $existing->map(fn ($account) => $account->id);

        $created = 0;

        foreach (Account::defaultAccounts() as $account) {
            $current = $existing->get($account['slug']);

            if ($current) {
                // Accounts created by the old inline provisioning have no Persian
                // name — the chart of accounts renders blank for fa/ps users.
                $this->fillNulls(Account::class, $current, [
                    'local_name' => $account['local_name'] ?? null,
                    'parent_id' => isset($account['parent_slug'])
                        ? $accountIds->get($account['parent_slug'])
                        : null,
                ]);

                continue;
            }

            $typeId = $typeIds->get($account['account_type_slug']);

            if (! $typeId) {
                continue;
            }

            $model = $this->insert(Account::class, [
                'name' => $account['name'],
                'local_name' => $account['local_name'] ?? null,
                'number' => $account['number'],
                'account_type_id' => $typeId,
                'parent_id' => isset($account['parent_slug'])
                    ? $accountIds->get($account['parent_slug'])
                    : null,
                'company_id' => $company->id,
                'slug' => $account['slug'],
                'remark' => $account['remark'],
                'is_main' => $account['is_main'],
                'created_by' => $actorId,
            ]);

            $accountIds->put($account['slug'], $model->id);
            $created++;
        }

        return $created;
    }

    private function currencies(Company $company, ?string $actorId): int
    {
        $existing = $this->existing(Currency::class, $company, 'code');
        $created = 0;

        foreach (Currency::defaultCurrencies() as $currency) {
            if (in_array($currency['code'], $existing, true)) {
                continue;
            }

            $this->insert(Currency::class, [
                'name' => $currency['name'],
                'local_name' => $currency['local_name'] ?? null,
                'code' => $currency['code'],
                'symbol' => $currency['symbol'],
                'format' => $currency['format'],
                'exchange_rate' => $currency['exchange_rate'],
                'is_active' => $currency['is_active'],
                'is_base_currency' => $currency['is_base_currency'] ?? false,
                'flag' => $currency['flag'],
                'company_id' => $company->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function quantities(Company $company, ?string $actorId): int
    {
        $existing = $this->existing(Quantity::class, $company, 'slug');
        $created = 0;

        foreach (Quantity::defaultQuantity() as $quantity) {
            if (in_array($quantity['slug'], $existing, true)) {
                continue;
            }

            $this->insert(Quantity::class, [
                'quantity' => $quantity['quantity'],
                'unit' => $quantity['unit'],
                'symbol' => $quantity['symbol'],
                'slug' => $quantity['slug'],
                'company_id' => $company->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function unitMeasures(Company $company, ?string $actorId): int
    {
        $quantityIds = Quantity::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->pluck('id', 'slug');

        $existing = $this->existing(UnitMeasure::class, $company, 'name');
        $created = 0;

        foreach (UnitMeasure::defaultUnitMeasures() as $unitMeasure) {
            if (in_array($unitMeasure['name'], $existing, true)) {
                continue;
            }

            $quantityId = $quantityIds->get($unitMeasure['quantity_slug']);

            if (! $quantityId) {
                continue;
            }

            $this->insert(UnitMeasure::class, [
                'name' => $unitMeasure['name'],
                'unit' => $unitMeasure['unit'],
                'symbol' => $unitMeasure['symbol'],
                'company_id' => $company->id,
                'quantity_id' => $quantityId,
                'is_main' => true,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function sizes(Company $company, ?string $actorId): int
    {
        $existing = $this->existing(Size::class, $company, 'code');
        $created = 0;

        foreach (Size::defaultSizes() as $size) {
            if (in_array($size['code'], $existing, true)) {
                continue;
            }

            $this->insert(Size::class, [
                'name' => $size['name'],
                'code' => $size['code'],
                'company_id' => $company->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }


    private function cashCustomer(Company $company, ?string $actorId): int
    {
        $exists = Ledger::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', 'CASH-CUST')
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return 0;
        }

        $this->insert(Ledger::class, [
            'name' => __('general.cash_customer'),
            'code' => 'CASH-CUST',
            'type' => 'customer',
            'company_id' => $company->id,
            'created_by' => $actorId,
        ]);

        return 1;
    }

    private function expenseCategories(Company $company, ?string $actorId): int
    {
        $existing = $this->existing(ExpenseCategory::class, $company, 'name');
        $created = 0;

        foreach (ExpenseCategory::defaultCategories() as $category) {
            if (in_array($category['name'], $existing, true)) {
                continue;
            }

            $this->insert(ExpenseCategory::class, [
                'name' => $category['name'],
                'remarks' => $category['remarks'],
                'is_active' => true,
                'company_id' => $company->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function journalClasses(Company $company, ?string $actorId): int
    {
        $existing = $this->existing(JournalClass::class, $company, 'code');
        $created = 0;

        foreach (JournalClass::defaultClasses() as $class) {
            if (in_array($class['code'], $existing, true)) {
                continue;
            }

            $this->insert(JournalClass::class, [
                'name' => $class['name'],
                'code' => $class['code'],
                'description' => $class['description'],
                'company_id' => $company->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function categories(Company $company, ?string $actorId): int
    {
        $existing = $this->existing(Category::class, $company, 'name');
        $created = 0;

        foreach (Category::defaultCategories() as $category) {
            if (in_array($category['name'], $existing, true)) {
                continue;
            }

            $this->insert(Category::class, [
                'name' => $category['name'],
                'local_name' => $category['local_name'] ?? null,
                'remark' => $category['remark'],
                'is_active' => true,
                'company_id' => $company->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function brands(Company $company, ?string $actorId): int
    {
        $existing = $this->existing(Brand::class, $company, 'name');
        $created = 0;

        foreach (Brand::defaultBrands() as $brand) {
            if (in_array($brand['name'], $existing, true)) {
                continue;
            }

            $this->insert(Brand::class, [
                'name' => $brand['name'],
                'type' => $brand['type'] ?? null,
                'company_id' => $company->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function departments(Company $company, ?string $actorId): int
    {
        $existing = $this->existing(Department::class, $company, 'code');
        $created = 0;

        foreach (Department::defaultDepartments() as $department) {
            if (in_array($department['code'], $existing, true)) {
                continue;
            }

            $this->insert(Department::class, [
                'name' => $department['name'],
                'code' => $department['code'],
                'remark' => $department['remark'],
                'company_id' => $company->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Default work patterns.
     *
     * The Afghan private-sector week runs Saturday to Thursday with Friday as
     * the rest day, so that is the default; a five-day variant is provided for
     * NGO and public-sector branches, and a Ramadan profile with reduced hours
     * that HR activates for the month. All three are ordinary editable rows.
     */
    private function shifts(Company $company, ?string $actorId): int
    {
        $existing = $this->existing(Shift::class, $company, 'code');
        $created = 0;

        $defaults = [
            [
                'name' => 'General Shift (Sat–Thu)',
                'code' => 'GEN6',
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'break_minutes' => 60,
                'grace_in_minutes' => 15,
                'full_day_hours' => 8,
                'half_day_hours' => 4,
                // ISO weekdays: Sat(6), Sun(7), Mon(1), Tue(2), Wed(3), Thu(4).
                // Friday (5) is absent — it is the weekly rest day.
                'working_days' => [6, 7, 1, 2, 3, 4],
                'is_default' => true,
            ],
            [
                'name' => 'Five Day Shift (Sat–Wed)',
                'code' => 'GEN5',
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'break_minutes' => 60,
                'grace_in_minutes' => 15,
                'full_day_hours' => 8,
                'half_day_hours' => 4,
                'working_days' => [6, 7, 1, 2, 3],
                'is_default' => false,
            ],
            [
                'name' => 'Ramadan Shift',
                'code' => 'RAMADAN',
                'start_time' => '09:00:00',
                'end_time' => '15:00:00',
                'break_minutes' => 0,
                'grace_in_minutes' => 15,
                'full_day_hours' => 6,
                'half_day_hours' => 3,
                'working_days' => [6, 7, 1, 2, 3, 4],
                'is_default' => false,
            ],
        ];

        foreach ($defaults as $shift) {
            if (in_array($shift['code'], $existing, true)) {
                continue;
            }

            $this->insert(Shift::class, array_merge($shift, [
                // Left as an array — the model's `array` cast encodes it. Encoding
                // here too stored a JSON string inside the jsonb column, which came
                // back out of the cast as a string instead of a list of weekdays.
                'is_active' => true,
                'company_id' => $company->id,
                'created_by' => $actorId,
            ]));
            $created++;
        }

        return $created;
    }

    /**
     * Default leave types, following Afghan Labour Law as a starting point.
     *
     * Seeded as data so an employer can change any figure without a deploy —
     * the same principle as every other default in this service.
     */
    private function leaveTypes(Company $company, ?string $actorId): int
    {
        $existing = $this->existing(LeaveType::class, $company, 'code');
        $created = 0;

        foreach (LeaveType::defaultLeaveTypes() as $type) {
            if (in_array($type['code'], $existing, true)) {
                continue;
            }

            $this->insert(LeaveType::class, array_merge([
                'is_paid' => true,
                'requires_approval' => true,
                'requires_attachment' => false,
                'deduct_from_salary' => false,
                'is_encashable' => false,
                'pro_rata_on_join' => true,
                'excludes_holidays' => true,
                'excludes_weekends' => true,
                'is_active' => true,
            ], $type, [
                'company_id' => $company->id,
                'created_by' => $actorId,
            ]));
            $created++;
        }

        return $created;
    }

    /**
     * The components payroll creates for itself — tax, unpaid leave, loan
     * recovery, overtime. Looked up by code during calculation, so they must
     * exist before the first run.
     */
    private function salaryComponents(Company $company, ?string $actorId): int
    {
        $existing = $this->existing(SalaryComponent::class, $company, 'code');
        $created = 0;

        foreach (SalaryComponent::defaultComponents() as $component) {
            if (in_array($component['code'], $existing, true)) {
                continue;
            }

            $this->insert(SalaryComponent::class, array_merge([
                'affects_gross' => true,
                'is_active' => true,
            ], $component, [
                'company_id' => $company->id,
                'created_by' => $actorId,
            ]));
            $created++;
        }

        return $created;
    }

    /**
     * The statutory Afghan monthly wage tax table.
     *
     * Seeded as data with an effective-from date so a rate change is an edit
     * rather than a deploy — and so re-running an old period still reproduces
     * the tax that was withheld at the time.
     */
    private function taxBrackets(Company $company, ?string $actorId): int
    {
        $exists = TaxBracketSet::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('period', TaxPeriod::Monthly->value)
            ->exists();

        if ($exists) {
            return 0;
        }

        $currencyId = Currency::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_base_currency', true)
            ->value('id');

        /** @var TaxBracketSet $set */
        $set = $this->insert(TaxBracketSet::class, [
            'name' => 'Afghanistan Monthly Wage Tax',
            'jurisdiction' => 'AF',
            'period' => TaxPeriod::Monthly->value,
            // Deliberately far back: any historical period resolves to this set
            // rather than failing for want of a rate.
            'effective_from' => '2005-01-01',
            'currency_id' => $currencyId,
            'is_active' => true,
            'is_system' => true,
            'remark' => 'Seeded default. Verify against current Ministry of Finance rules.',
            'company_id' => $company->id,
            'created_by' => $actorId,
        ]);

        foreach (TaxBracketSet::defaultAfghanMonthlyBrackets() as $bracket) {
            $this->insert(TaxBracket::class, array_merge($bracket, [
                'tax_bracket_set_id' => $set->id,
                'company_id' => $company->id,
                'created_by' => $actorId,
            ]));
        }

        return 1;
    }

    /**
     * One open financial period covering the current year in the company calendar.
     */
    private function financialPeriod(Company $company, ?string $actorId): int
    {
        $exists = FinancialPeriod::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->exists();

        if ($exists) {
            return 0;
        }

        [$name, $start, $end] = $this->currentYearBounds();

        $this->insert(FinancialPeriod::class, [
            'name' => $name,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => FinancialPeriodStatus::Open->value,
            'company_id' => $company->id,
            'created_by' => $actorId,
        ]);

        return 1;
    }

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon}
     */
    private function currentYearBounds(): array
    {
        $calendar = Auth::user()?->company?->calendar_type?->value;

        if ($calendar === 'jalali') {
            $today = Jalalian::now();
            $start = (new Jalalian($today->getYear(), 1, 1))->toCarbon()->startOfDay();

            return [
                (string) $today->getYear(),
                $start,
                $start->copy()->addYear()->subDay(),
            ];
        }

        $start = Carbon::now()->startOfYear();

        return [(string) $start->year, $start, $start->copy()->endOfYear()];
    }

    /**
     * A user to attribute provisioning to when it runs without an authenticated user
     * (console backfill). created_by is not nullable on several of these tables.
     */
    private function fallbackActorId(): ?string
    {
        return User::query()
            ->withoutGlobalScopes()
            ->orderBy('created_at')
            ->value('id');
    }
}

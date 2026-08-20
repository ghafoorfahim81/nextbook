<?php

namespace App\Services;

use App\Enums\FinancialPeriodStatus;
use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Accounting\FinancialPeriod;
use App\Models\Administration\Brand;
use App\Models\Administration\Branch;
use App\Models\Administration\Category;
use App\Models\Administration\Currency;
use App\Models\Administration\Department;
use App\Models\Administration\Quantity;
use App\Models\Administration\Size;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\Expense\ExpenseCategory;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Shift;
use App\Models\JournalEntry\JournalClass;
use App\Models\Ledger\Ledger;
use App\Models\User;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;
use Symfony\Component\Uid\Ulid;

/**
 * Creates the default data a branch needs in order to trade.
 *
 * Two properties matter here and neither was true when this lived inline in
 * BranchController::store():
 *
 *  - It is atomic. Roughly 200 inserts either all land or none do, so a failure
 *    part-way through can no longer leave a branch with accounts but no currencies.
 *  - It is idempotent. Every set is matched on its natural key and only what is
 *    missing gets written, so the same method backfills branches that were created
 *    before a default was added to the codebase.
 */
class BranchProvisioningService
{
    /**
     * Provision (or backfill) every default set for a branch.
     *
     * @return array<string, int> rows created per module
     */
    public function provision(Branch $branch, ?string $actorId = null): array
    {
        $actorId = $actorId ?? Auth::id() ?? $this->fallbackActorId();

        $created = DB::transaction(function () use ($branch, $actorId): array {
            // Account types first — accounts resolve their type by slug within
            // this branch, and the default account list reads account types too.
            $counts = ['account_types' => $this->accountTypes($branch, $actorId)];

            $counts['accounts'] = $this->accounts($branch, $actorId);
            $counts['currencies'] = $this->currencies($branch, $actorId);
            $counts['quantities'] = $this->quantities($branch, $actorId);
            $counts['unit_measures'] = $this->unitMeasures($branch, $actorId);
            $counts['sizes'] = $this->sizes($branch, $actorId);
            $counts['warehouses'] = $this->warehouse($branch, $actorId);
            $counts['ledgers'] = $this->cashCustomer($branch, $actorId);
            $counts['expense_categories'] = $this->expenseCategories($branch, $actorId);
            $counts['journal_classes'] = $this->journalClasses($branch, $actorId);
            $counts['categories'] = $this->categories($branch, $actorId);
            $counts['brands'] = $this->brands($branch, $actorId);
            $counts['departments'] = $this->departments($branch, $actorId);
            $counts['shifts'] = $this->shifts($branch, $actorId);
            $counts['leave_types'] = $this->leaveTypes($branch, $actorId);
            $counts['financial_periods'] = $this->financialPeriod($branch, $actorId);

            return $counts;
        });

        // Posting accounts and the base currency may have just changed for this branch.
        BranchContext::flush($branch->id);

        return $created;
    }

    /**
     * Existing values of a column for one branch, ignoring the branch scope so this
     * works from a console command as well as from a request.
     *
     * Soft-deleted rows count as present: a default the user deleted on purpose
     * should stay deleted, not come back as a duplicate on the next sync.
     *
     * @return array<int, string>
     */
    private function existing(string $modelClass, Branch $branch, string $column): array
    {
        return $modelClass::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->pluck($column)
            ->filter()
            ->all();
    }

    /**
     * Insert a row with events muted.
     *
     * Muting events keeps provisioning from writing ~200 activity-log entries and
     * cache flushes, but it also disables the ULID and audit-column hooks — so id,
     * branch_id and created_by are always set explicitly here.
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

    private function accountTypes(Branch $branch, ?string $actorId): int
    {
        $existing = AccountType::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
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
                'branch_id' => $branch->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function accounts(Branch $branch, ?string $actorId): int
    {
        $typeIds = AccountType::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->pluck('id', 'slug');

        // Slug => account for everything this branch already has, so entries that
        // declare a parent_slug link to the right parent whether it was created
        // just now or on an earlier run.
        $existing = Account::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
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
                'branch_id' => $branch->id,
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

    private function currencies(Branch $branch, ?string $actorId): int
    {
        $existing = $this->existing(Currency::class, $branch, 'code');
        $created = 0;

        foreach (Currency::defaultCurrencies() as $currency) {
            if (in_array($currency['code'], $existing, true)) {
                continue;
            }

            $this->insert(Currency::class, [
                'name' => $currency['name'],
                'code' => $currency['code'],
                'symbol' => $currency['symbol'],
                'format' => $currency['format'],
                'exchange_rate' => $currency['exchange_rate'],
                'is_active' => $currency['is_active'],
                'is_base_currency' => $currency['is_base_currency'] ?? false,
                'flag' => $currency['flag'],
                'branch_id' => $branch->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function quantities(Branch $branch, ?string $actorId): int
    {
        $existing = $this->existing(Quantity::class, $branch, 'slug');
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
                'branch_id' => $branch->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function unitMeasures(Branch $branch, ?string $actorId): int
    {
        $quantityIds = Quantity::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->pluck('id', 'slug');

        $existing = $this->existing(UnitMeasure::class, $branch, 'name');
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
                'branch_id' => $branch->id,
                'quantity_id' => $quantityId,
                'is_main' => true,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function sizes(Branch $branch, ?string $actorId): int
    {
        $existing = $this->existing(Size::class, $branch, 'code');
        $created = 0;

        foreach (Size::defaultSizes() as $size) {
            if (in_array($size['code'], $existing, true)) {
                continue;
            }

            $this->insert(Size::class, [
                'name' => $size['name'],
                'code' => $size['code'],
                'branch_id' => $branch->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function warehouse(Branch $branch, ?string $actorId): int
    {
        $exists = Warehouse::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->exists();

        if ($exists) {
            return 0;
        }

        // Named from translations rather than copied off whatever main warehouse
        // happened to exist in another branch.
        $this->insert(Warehouse::class, [
            'name' => __('general.main_warehouse'),
            'address' => __('general.main_warehouse'),
            'branch_id' => $branch->id,
            'is_main' => true,
            'created_by' => $actorId,
        ]);

        return 1;
    }

    private function cashCustomer(Branch $branch, ?string $actorId): int
    {
        $exists = Ledger::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
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
            'branch_id' => $branch->id,
            'created_by' => $actorId,
        ]);

        return 1;
    }

    private function expenseCategories(Branch $branch, ?string $actorId): int
    {
        $existing = $this->existing(ExpenseCategory::class, $branch, 'name');
        $created = 0;

        foreach (ExpenseCategory::defaultCategories() as $category) {
            if (in_array($category['name'], $existing, true)) {
                continue;
            }

            $this->insert(ExpenseCategory::class, [
                'name' => $category['name'],
                'remarks' => $category['remarks'],
                'is_active' => true,
                'branch_id' => $branch->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function journalClasses(Branch $branch, ?string $actorId): int
    {
        $existing = $this->existing(JournalClass::class, $branch, 'code');
        $created = 0;

        foreach (JournalClass::defaultClasses() as $class) {
            if (in_array($class['code'], $existing, true)) {
                continue;
            }

            $this->insert(JournalClass::class, [
                'name' => $class['name'],
                'code' => $class['code'],
                'description' => $class['description'],
                'branch_id' => $branch->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function categories(Branch $branch, ?string $actorId): int
    {
        $existing = $this->existing(Category::class, $branch, 'name');
        $created = 0;

        foreach (Category::defaultCategories() as $category) {
            if (in_array($category['name'], $existing, true)) {
                continue;
            }

            $this->insert(Category::class, [
                'name' => $category['name'],
                'remark' => $category['remark'],
                'is_active' => true,
                'branch_id' => $branch->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function brands(Branch $branch, ?string $actorId): int
    {
        $existing = $this->existing(Brand::class, $branch, 'name');
        $created = 0;

        foreach (Brand::defaultBrands() as $brand) {
            if (in_array($brand['name'], $existing, true)) {
                continue;
            }

            $this->insert(Brand::class, [
                'name' => $brand['name'],
                'type' => $brand['type'] ?? null,
                'branch_id' => $branch->id,
                'created_by' => $actorId,
            ]);
            $created++;
        }

        return $created;
    }

    private function departments(Branch $branch, ?string $actorId): int
    {
        $existing = $this->existing(Department::class, $branch, 'code');
        $created = 0;

        foreach (Department::defaultDepartments() as $department) {
            if (in_array($department['code'], $existing, true)) {
                continue;
            }

            $this->insert(Department::class, [
                'name' => $department['name'],
                'code' => $department['code'],
                'remark' => $department['remark'],
                'branch_id' => $branch->id,
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
    private function shifts(Branch $branch, ?string $actorId): int
    {
        $existing = $this->existing(Shift::class, $branch, 'code');
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
                'working_days' => json_encode($shift['working_days']),
                'is_active' => true,
                'branch_id' => $branch->id,
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
    private function leaveTypes(Branch $branch, ?string $actorId): int
    {
        $existing = $this->existing(LeaveType::class, $branch, 'code');
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
                'branch_id' => $branch->id,
                'created_by' => $actorId,
            ]));
            $created++;
        }

        return $created;
    }

    /**
     * One open financial period covering the current year in the company calendar.
     */
    private function financialPeriod(Branch $branch, ?string $actorId): int
    {
        $exists = FinancialPeriod::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
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
            'branch_id' => $branch->id,
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

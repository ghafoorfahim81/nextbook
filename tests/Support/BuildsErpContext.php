<?php

namespace Tests\Support;

use App\Enums\CostingMethod;
use App\Enums\CalendarType;
use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Administration\Currency;
use App\Models\Administration\Quantity;
use App\Models\Administration\Size;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\Inventory\Item;
use App\Models\Ledger\Ledger;
use App\Models\Role;
use App\Models\User;
use App\Support\CompanyDatabaseContext;
use Illuminate\Support\Facades\Cache;

trait BuildsErpContext
{
    protected function bootstrapErpContext(string $costingMethod = CostingMethod::FIFO->value): array
    {
        // The company comes first and nothing is created before it. A branch
        // requires an owning company, the user's company has to be the same one
        // or every company-scoped query in the test would look at a different
        // set of books than the branch does, and — since step 10 — the database
        // has to be told which company is acting before anything is written in
        // its name. `currency_id` is filled in further down, once a currency
        // exists.
        $company = Company::factory()->create([
            'name_en' => 'ERP Test Company',
            'costing_method' => $costingMethod,
            'calendar_type' => CalendarType::GREGORIAN->value,
        ]);

        // The acting company, bound in the container and set on the connection
        // in the same breath — which is the invariant SetDatabaseCompanyContext
        // maintains in production, and for the same reason.
        //
        // The suite connects as the application role, so the row level security
        // policies apply and reject a write stamped with any company but the
        // one on the connection. Everything below this line would be refused
        // without the second line: not only the chart of accounts, the ledgers
        // and the item, but the audit log row each of their creations writes.
        //
        // And without the first line the two would disagree. ActivityLogService
        // reads the company from the container, so a test bootstrapping a
        // second context mid-test — StockServiceTest does, for a LIFO company —
        // wrote log rows naming the *first* company onto a connection acting
        // for the second. The bindings used to be set at the end of this
        // method, which was too late for everything audited in between.
        app()->instance('active_company_id', $company->id);
        app()->instance('accessible_company_ids', [$company->id]);
        CompanyDatabaseContext::set($company->id);

        // Created with its company rather than attached afterwards. The old
        // order built the user first and updated it later, which left two
        // audited writes attributed to a company that was not yet on the
        // connection — harmless on the first call, but a test that bootstraps a
        // second context mid-test (StockServiceTest does, for a LIFO company)
        // had the first context's company still bound, and the write was
        // refused. Creating the user in its own company makes the order of
        // calls stop mattering.
        $user = User::factory()->create([
            'name' => 'erp-super-admin',
            'email' => 'erp-admin-'.fake()->unique()->numberBetween(1000, 9999).'@example.test',
            'preferences' => \App\Support\Settings::defaults(),
            'company_id' => $company->id,
        ]);

        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'name' => 'Main Branch '.fake()->unique()->numberBetween(100, 999),
            'is_main' => true,
            'created_by' => $user->id,
        ]);

        $role = Role::query()->firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'web'],
            ['slug' => 'super-admin']
        );

        if ($role->slug !== 'super-admin') {
            $role->slug = 'super-admin';
            $role->save();
        }

        $user->assignRole($role);
        $user->update(['branch_id' => $branch->id]);
        $this->actingAs($user);
        app()->instance('active_branch_id', $branch->id);

        $quantity = Quantity::factory()->create([
            'company_id' => $company->id,
            'quantity' => 'Count',
            'unit' => 'piece',
            'symbol' => 'pc',
            'slug' => 'count',
            'is_main' => true,
        ]);

        $unitMeasure = UnitMeasure::factory()->create([
            'company_id' => $company->id,
            'quantity_id' => $quantity->id,
            'name' => 'Piece',
            'unit' => '1',
            'symbol' => 'pc',
            'is_main' => true,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'Main Warehouse',
            'is_main' => true,
        ]);

        $size = Size::factory()->create([
            'company_id' => $company->id,
            'name' => 'Default',
            'code' => 'DF',
        ]);

        $currency = Currency::factory()->create([
            'company_id' => $company->id,
            'name' => 'Afghani',
            'code' => 'AFN',
            'symbol' => 'Af',
            'exchange_rate' => 1,
            'is_active' => true,
            'is_base_currency' => true,
            'flag' => 'af.png',
        ]);

        $company->update(['currency_id' => $currency->id]);

        $user->refresh();
        $this->actingAs($user);
        app()->instance('active_branch_id', $branch->id);
        app()->instance('active_company_id', $company->id);
        app()->instance('accessible_company_ids', [$company->id]);

        $accountTypes = $this->createDefaultAccountTypes($company->id);
        $accounts = $this->createDefaultGlAccounts($company->id, $accountTypes);

        $customerLedger = Ledger::factory()->create([
            'company_id' => $company->id,
            'currency_id' => $currency->id,
            'name' => 'Customer Ledger',
            'code' => 'CUS-001',
            'type' => 'customer',
            'is_active' => true,
        ]);

        $supplierLedger = Ledger::factory()->create([
            'company_id' => $company->id,
            'currency_id' => $currency->id,
            'name' => 'Supplier Ledger',
            'code' => 'SUP-001',
            'type' => 'supplier',
            'is_active' => true,
        ]);

        $item = Item::factory()->create([
            'company_id' => $company->id,
            'unit_measure_id' => $unitMeasure->id,
            'cost_account_id' => $accounts['cost-of-goods-sold']->id,
            'income_account_id' => $accounts['product-income']->id,
            'asset_account_id' => $accounts['inventory-stock']->id,
            'name' => 'Test Item',
            'code' => 'ITEM-001',
            'sku' => 'SKU-001',
            'minimum_stock' => 5,
            'maximum_stock' => 100,
            'is_batch_tracked' => false,
            'is_expiry_tracked' => false,
        ]);

        Cache::put('home_currency', $currency);
        Cache::put('gl_accounts', collect($accounts)->mapWithKeys(fn (Account $account, string $slug) => [$slug => $account->id]));

        return [
            'user' => $user,
            'branch' => $branch,
            'company' => $company,
            'currency' => $currency,
            'quantity' => $quantity,
            'unit_measure' => $unitMeasure,
            'warehouse' => $warehouse,
            'size' => $size,
            'customer_ledger' => $customerLedger,
            'supplier_ledger' => $supplierLedger,
            'item' => $item,
            'account_types' => $accountTypes,
            'accounts' => $accounts,
        ];
    }

    /**
     * Build fixtures as though another company's own users were building them.
     *
     * Three things have to move together, and this is the only place that knows
     * it:
     *
     *  - `active_company_id` / `accessible_company_ids`, which ActivityLogService
     *    and HasCompany read to decide which company a new row belongs to.
     *  - the company on the connection, which the row level security policies
     *    compare that answer against.
     *  - `active_branch_id`, cleared because this company's branches are not the
     *    acting one's. Leaving it set is what broke the first attempt at this
     *    helper: HasCompany prefers the active branch, so rows created in here
     *    were stamped with the *previous* company and then refused by the
     *    policies — including the throwaway user BranchFactory builds for
     *    `created_by`.
     *
     * Setting only the connection is not enough, and setting only the bindings
     * is worse than useless: the two disagreeing is exactly what the policies
     * exist to catch.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    protected function actingInCompany(string $companyId, callable $callback): mixed
    {
        $previousCompany = app()->bound('active_company_id') ? app('active_company_id') : null;
        $previousAccessible = app()->bound('accessible_company_ids') ? app('accessible_company_ids') : [];
        $previousBranch = app()->bound('active_branch_id') ? app('active_branch_id') : null;

        app()->instance('active_company_id', $companyId);
        app()->instance('accessible_company_ids', [$companyId]);
        app()->instance('active_branch_id', null);

        try {
            return CompanyDatabaseContext::runFor($companyId, $callback);
        } finally {
            app()->instance('active_company_id', $previousCompany);
            app()->instance('accessible_company_ids', $previousAccessible);
            app()->instance('active_branch_id', $previousBranch);
        }
    }

    /**
     * A branch belonging to a company of its own, built in that company's
     * context.
     *
     * Every test that proves the company boundary holds needs a row on the far
     * side of it, and `Branch::factory()` alone does not give one that can
     * exist: creating a branch is audited, and the policies take an activity log
     * row only from a connection acting for the company it names. Building it
     * the way that company's own users would is also what makes the crossing
     * the test then attempts a real one, rather than a write that was never
     * possible in the first place.
     *
     * Anything else needed on the far side goes in the same shape:
     * `$this->actingInCompany($branch->company_id, fn () => ...)`.
     */
    protected function branchInAnotherCompany(array $attributes = []): Branch
    {
        $company = Company::factory()->create([
            'name_en' => 'Other Company '.fake()->unique()->numberBetween(1000, 9999),
        ]);

        return $this->actingInCompany($company->id, fn (): Branch => Branch::factory()->create(array_merge([
            'company_id' => $company->id,
            'is_main' => false,
        ], $attributes)));
    }

    protected function createDefaultAccountTypes(string $companyId): array
    {
        $definitions = [
            'cash-or-bank' => ['name' => 'Cash or Bank', 'nature' => 'asset'],
            'account-receivable' => ['name' => 'Accounts Receivable', 'nature' => 'asset'],
            'account-payable' => ['name' => 'Accounts Payable', 'nature' => 'liability'],
            'income' => ['name' => 'Income', 'nature' => 'income'],
            'cost-of-goods-sold' => ['name' => 'Cost of Goods Sold', 'nature' => 'expense'],
            'other-current-asset' => ['name' => 'Other Current Asset', 'nature' => 'asset'],
            'equity' => ['name' => 'Equity', 'nature' => 'equity'],
            'expense' => ['name' => 'Expense', 'nature' => 'expense'],
        ];

        $types = [];
        foreach ($definitions as $slug => $definition) {
            $types[$slug] = AccountType::factory()->create([
                'company_id' => $companyId,
                'name' => $definition['name'],
                'slug' => $slug,
                'nature' => $definition['nature'],
                'is_main' => true,
            ]);
        }

        return $types;
    }

    protected function createDefaultGlAccounts(string $companyId, array $accountTypes): array
    {
        $definitions = [
            'sales-revenue' => ['name' => 'Sales Revenue', 'type' => 'income', 'number' => '7001'],
            'account-receivable' => ['name' => 'Accounts Receivable', 'type' => 'account-receivable', 'number' => '2001'],
            'account-payable' => ['name' => 'Accounts Payable', 'type' => 'account-payable', 'number' => '5001'],
            'product-income' => ['name' => 'Product Income', 'type' => 'income', 'number' => '7002'],
            'cash-in-hand' => ['name' => 'Cash In Hand', 'type' => 'cash-or-bank', 'number' => '1001'],
            // A second cash account, so tests can prove a voucher reports the
            // account the money actually went to rather than a default.
            'cash-in-safe' => ['name' => 'Cash In Safe', 'type' => 'cash-or-bank', 'number' => '1002'],
            'cost-of-goods-sold' => ['name' => 'Cost Of Goods Sold', 'type' => 'cost-of-goods-sold', 'number' => '8001'],
            'inventory-stock' => ['name' => 'Inventory Stock', 'type' => 'other-current-asset', 'number' => '3001'],
            'landed-costs-clearing' => ['name' => 'Landed Costs Clearing', 'type' => 'other-current-asset', 'number' => '3060'],
            'retained-earnings' => ['name' => 'Retained Earnings', 'type' => 'equity', 'number' => '6001'],
            'opening-balance-equity' => ['name' => 'Opening Balance Equity', 'type' => 'equity', 'number' => '6002'],
            'non-inventory-items' => ['name' => 'Non Inventory Items', 'type' => 'other-current-asset', 'number' => '3002'],
            'raw-materials' => ['name' => 'Raw Materials', 'type' => 'other-current-asset', 'number' => '3003'],
            'finished-goods' => ['name' => 'Finished Goods', 'type' => 'other-current-asset', 'number' => '3004'],
            'other-expenses' => ['name' => 'Other Expenses', 'type' => 'expense', 'number' => '9001'],
            // Freight for moving goods between the company's own warehouses.
            // An item transfer defaults its expense line here.
            'item-transfer-expense' => ['name' => 'Item Transfer Expense', 'type' => 'expense', 'number' => '9801'],
            'contra-clearing' => ['name' => 'Set-off Clearing', 'type' => 'other-current-asset', 'number' => '3070'],
            // Phase 4: a transfer to another branch, between send and receipt,
            // and what goes missing on the way.
            'goods-in-transit' => ['name' => 'Goods in Transit', 'type' => 'other-current-asset', 'number' => '3025'],
            'inventory-shrinkage-and-wastage' => ['name' => 'Inventory Shrinkage and Wastage', 'type' => 'expense', 'number' => '9040'],
            // Realised exchange differences. Resolved by slug everywhere —
            // the names are localised into Dari and Pashto in production.
            'fx-gain' => ['name' => 'Foreign Exchange Gain', 'type' => 'income', 'number' => '9070'],
            'fx-loss' => ['name' => 'Foreign Exchange Loss', 'type' => 'expense', 'number' => '9080'],
            // Where an overpayment lands. Liability to the customer, asset
            // against the supplier.
            'customer-advances' => ['name' => 'Customer Advances', 'type' => 'account-payable', 'number' => '5085'],
            'supplier-advances' => ['name' => 'Supplier Advances', 'type' => 'other-current-asset', 'number' => '4075'],
            // Payroll. Salary payable is kept out of trade payables so staff
            // cost stays separable, and withheld wage tax is a liability
            // (money held for the MoF) rather than a second expense on top of
            // the gross that was already booked.
            'payroll-liabilities' => ['name' => 'Payroll Liabilities', 'type' => 'account-payable', 'number' => '5081'],
            'salary-tax-payable' => ['name' => 'Salary Tax Payable', 'type' => 'account-payable', 'number' => '5084'],
            'employee-advances' => ['name' => 'Employee Advances', 'type' => 'other-current-asset', 'number' => '4076'],
            'employee-loans-receivable' => ['name' => 'Employee Loans Receivable', 'type' => 'other-current-asset', 'number' => '4077'],
            'permanent-staff-salary' => ['name' => 'Permanent Staff Salary', 'type' => 'expense', 'number' => '9201'],
            'temporary-staff-salary' => ['name' => 'Temporary Staff Salary', 'type' => 'expense', 'number' => '9202'],
            'consultant-professional-salary' => ['name' => 'Consultant Salary', 'type' => 'expense', 'number' => '9207'],
            'allowances-commissions' => ['name' => 'Allowances & Commissions', 'type' => 'expense', 'number' => '9208'],
            'overtime-expense' => ['name' => 'Overtime Expense', 'type' => 'expense', 'number' => '9203'],
            // Where an abandoned staff loan lands. An expense, not a bad debt:
            // it was never revenue, so there is nothing to reverse out.
            'staff-benefits-expense' => ['name' => 'Staff Benefits', 'type' => 'expense', 'number' => '9204'],
        ];

        $accounts = [];
        foreach ($definitions as $slug => $definition) {
            $accounts[$slug] = Account::factory()->create([
                'company_id' => $companyId,
                'name' => $definition['name'],
                'number' => $definition['number'],
                'slug' => $slug,
                'account_type_id' => $accountTypes[$definition['type']]->id,
                'is_main' => true,
                'is_active' => true,
            ]);
        }

        return $accounts;
    }
}

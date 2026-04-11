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
use Illuminate\Support\Facades\Cache;

trait BuildsErpContext
{
    protected function bootstrapErpContext(string $costingMethod = CostingMethod::FIFO->value): array
    {
        $user = User::factory()->create([
            'name' => 'erp-super-admin',
            'email' => 'erp-admin-'.fake()->unique()->numberBetween(1000, 9999).'@example.test',
            'preferences' => User::DEFAULT_PREFERENCES,
        ]);

        $branch = Branch::factory()->create([
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
            'branch_id' => $branch->id,
            'quantity' => 'Count',
            'unit' => 'piece',
            'symbol' => 'pc',
            'slug' => 'count',
            'is_main' => true,
        ]);

        $unitMeasure = UnitMeasure::factory()->create([
            'branch_id' => $branch->id,
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
            'branch_id' => $branch->id,
            'name' => 'Default',
            'code' => 'DF',
        ]);

        $currency = Currency::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'Afghani',
            'code' => 'AFN',
            'symbol' => 'Af',
            'exchange_rate' => 1,
            'is_active' => true,
            'is_base_currency' => true,
            'flag' => 'af.png',
        ]);

        $company = Company::factory()->create([
            'name_en' => 'ERP Test Company',
            'currency_id' => $currency->id,
            'costing_method' => $costingMethod,
            'calendar_type' => CalendarType::GREGORIAN->value,
        ]);

        $user->update(['company_id' => $company->id]);
        $user->refresh();
        $this->actingAs($user);
        app()->instance('active_branch_id', $branch->id);

        $accountTypes = $this->createDefaultAccountTypes($branch->id);
        $accounts = $this->createDefaultGlAccounts($branch->id, $accountTypes);

        $customerLedger = Ledger::factory()->create([
            'branch_id' => $branch->id,
            'currency_id' => $currency->id,
            'name' => 'Customer Ledger',
            'code' => 'CUS-001',
            'type' => 'customer',
            'is_active' => true,
        ]);

        $supplierLedger = Ledger::factory()->create([
            'branch_id' => $branch->id,
            'currency_id' => $currency->id,
            'name' => 'Supplier Ledger',
            'code' => 'SUP-001',
            'type' => 'supplier',
            'is_active' => true,
        ]);

        $item = Item::factory()->create([
            'branch_id' => $branch->id,
            'unit_measure_id' => $unitMeasure->id,
            'size_id' => $size->id,
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

    protected function createDefaultAccountTypes(string $branchId): array
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
                'branch_id' => $branchId,
                'name' => $definition['name'],
                'slug' => $slug,
                'nature' => $definition['nature'],
                'is_main' => true,
            ]);
        }

        return $types;
    }

    protected function createDefaultGlAccounts(string $branchId, array $accountTypes): array
    {
        $definitions = [
            'sales-revenue' => ['name' => 'Sales Revenue', 'type' => 'income', 'number' => '7001'],
            'account-receivable' => ['name' => 'Accounts Receivable', 'type' => 'account-receivable', 'number' => '2001'],
            'account-payable' => ['name' => 'Accounts Payable', 'type' => 'account-payable', 'number' => '5001'],
            'product-income' => ['name' => 'Product Income', 'type' => 'income', 'number' => '7002'],
            'cash-in-hand' => ['name' => 'Cash In Hand', 'type' => 'cash-or-bank', 'number' => '1001'],
            'cost-of-goods-sold' => ['name' => 'Cost Of Goods Sold', 'type' => 'cost-of-goods-sold', 'number' => '8001'],
            'inventory-stock' => ['name' => 'Inventory Stock', 'type' => 'other-current-asset', 'number' => '3001'],
            'retained-earnings' => ['name' => 'Retained Earnings', 'type' => 'equity', 'number' => '6001'],
            'opening-balance-equity' => ['name' => 'Opening Balance Equity', 'type' => 'equity', 'number' => '6002'],
            'non-inventory-items' => ['name' => 'Non Inventory Items', 'type' => 'other-current-asset', 'number' => '3002'],
            'raw-materials' => ['name' => 'Raw Materials', 'type' => 'other-current-asset', 'number' => '3003'],
            'finished-goods' => ['name' => 'Finished Goods', 'type' => 'other-current-asset', 'number' => '3004'],
            'other-expenses' => ['name' => 'Other Expenses', 'type' => 'expense', 'number' => '9001'],
        ];

        $accounts = [];
        foreach ($definitions as $slug => $definition) {
            $accounts[$slug] = Account::factory()->create([
                'branch_id' => $branchId,
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

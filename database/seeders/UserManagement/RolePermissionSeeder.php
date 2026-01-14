<?php

namespace Database\Seeders\UserManagement;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Permission;
use App\Models\Administration\Branch;
use App\Models\Role;
use Symfony\Component\Uid\Ulid;
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Resources (models / domains)
        |--------------------------------------------------------------------------
        */
        $resources = [
            'users',
            'roles',
            'employees',
            'payrolls',
            'salary_payments',
            'attendances',
            'leaves',
            'leave_applications',
            'leave_types',
            'departments',
            'designations',
            'prepaids',
            'loans',

            'items',
            'categories',
            'currencies',
            'unit_measures',
            'sizes',
            'companies',
            'brands',
            'stores',
            'branches',

            'accounts',
            'account_transfers',
            'account_types',
            'journals',
            'pos_transactions',

            'dashboard',
            'preferences',
            'notifications',
            'projects',

            'owners',
            'drawings',

            'customers',
            'suppliers',

            'purchases',
            'purchase_returns',
            'purchase_orders',
            'purchase_quotations',

            'sales',
            'sale_returns',
            'sale_orders',
            'sale_quotations',

            'receipts',
            'payments',
            'expenses',
            'expense_categories',

            'reports',

            'stock_transfer',
            'stock_receive',
            'stock_issue',
            'stock_adjustment',
            'item_transfers',

            'formulas',
            'manufacturers',

            'backup',
        ];

        /*
        |--------------------------------------------------------------------------
        | 2. Actions
        |--------------------------------------------------------------------------
        */
        $baseActions = [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'import',
            'export',
        ];

        $transactionActions = array_merge($baseActions, [
            'print',
            'approve',
            'reject',
            'email',
        ]);

        $transactionResources = [
            'purchases',
            'purchase_returns',
            'purchase_orders',
            'sales',
            'sale_returns',
            'sale_orders',
            'receipts',
            'payments',
            'account_transfers',
            'drawings',
            'item_transfers',
        ];

        /*
        |--------------------------------------------------------------------------
        | 3. Create Permissions
        |--------------------------------------------------------------------------
        */
        foreach ($resources as $resource) {
            $actions = in_array($resource, $transactionResources, true)
                ? $transactionActions
                : $baseActions;

            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'id' => (string) new Ulid(),
                    'name' => "{$resource}.{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Create Roles
        |--------------------------------------------------------------------------
        */
        $superAdmin = Role::firstOrCreate([
            'name' => 'super-admin',
            'slug' => 'super-admin',
            'guard_name' => 'web',
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'slug' => 'admin',
            'guard_name' => 'web',
        ]);

        $accountant = Role::firstOrCreate([
            'name' => 'accountant',
            'slug' => 'accountant',
            'guard_name' => 'web',
        ]);

        $clerk = Role::firstOrCreate([
            'name' => 'clerk',
            'slug' => 'clerk',
            'guard_name' => 'web',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 5. Assign Permissions to Roles
        |--------------------------------------------------------------------------
        */

        // Super Admin → ALL permissions
        $superAdmin->syncPermissions(Permission::all());

        // Admin → everything except deleting users & roles
        $admin->syncPermissions(
            Permission::whereNotIn('name', [
                'users.delete',
                'roles.delete',
            ])->get()
        );

        // Accountant → transactional + reports (NO delete)
        $accountantPermissions = Permission::whereIn('name', [
            // Reports
            'reports.view',
            'reports.export',

            // Purchases
            'purchases.view',
            'purchases.create',
            'purchases.update',
            'purchases.approve',
            'purchases.print',

            // Sales
            'sales.view',
            'sales.create',
            'sales.update',
            'sales.approve',
            'sales.print',

            // Receipts & Payments
            'receipts.view',
            'receipts.create',
            'payments.view',
            'payments.create',
        ])->get();

        $accountant->syncPermissions($accountantPermissions);

        // Clerk → view-only access
        $clerk->syncPermissions(
            Permission::where('name', 'like', '%.view')->get()
        );

        /*
        |--------------------------------------------------------------------------
        | 6. Assign Super Admin Role to Default User (optional)
        |--------------------------------------------------------------------------
        */
        $superAdminUser = User::where('email', 'admin@nextbook.com')->first();

        if ($superAdminUser) {
            $superAdminUser->syncRoles(['super-admin']);
        }
    }
}

<?php

namespace Database\Seeders\UserManagement;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resources = [
            'administration',
            'user_management',
            'owners',
            'drawing',
            "employees",
            'inventory',
            'reports',
            'account',
            'purchase',
            'sale',
            'ledger',
            'receipt',
            'payment',
            'account_transfer',
            'backup',
            'sale_quotation',
            'purchase_quotation',
            "sale_return",
            "purchase_return",
            "sale_order",
            "purchase_order",
            'stock_transfer',
            'stock_receive',
            'stock_issue',
            'stock_adjustment', 
        ];

        $transaction_resources = [
            'sale',
            'sale_return',
            'sale_order',
            'purchase',
            'purchase_return',
            'purchase_order',
            'receipt',
            'payment',
            'account_transfer',
            "drawing",
            'reports',
        ];
        // 'print', 'approve', 'reject', 'approve','email'
        // Define actions
        $actions = ['list','create', 'edit', 'view', 'delete', 'import', 'export', ];


        // Create permissions for each resource
        $permissions = [];

        foreach ($resources as $resource) {
            if (in_array($resource, $transaction_resources) ) {
                $actions = array_merge($actions, ['print', 'approve', 'reject', 'approve','email']);
                foreach ($actions as $action) {
                    $permissions[] = "{$action}_{$resource}";
                }
            } 
            else {
                foreach ($actions as $action) {
                    $permissions[] = "{$action}_{$resource}";
                }
            }
        }

        // Insert permissions into the database
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $accountant = Role::firstOrCreate(['name' => 'Accountant']);
        $clerk = Role::firstOrCreate(['name' => 'Clerk']);

        // Assign all permissions to Super Admin
        $superAdmin->givePermissionTo(Permission::all());

        // Assign permissions to Admin (excluding restricted ones)
        $adminPermissions = Permission::whereNotIn('name', [
            'delete_user_management',
            'create_account',
            'edit_account',
            'list_account',
            'delete_account',
            'import_account',
            'export_account',
            'print_account',
            'approve_account',
            'reject_account',
            'approve_account',
            'email_account',
        ])->get();
        $admin->syncPermissions($adminPermissions);

        // Assign limited permissions to Editor (only edit, view_list, and view)
        $accountantPermissions = Permission::where(function ($query) {
            $query->where('name', 'like', 'edit_%')
                ->orWhere('name', 'like', 'list_%')
                ->orWhere('name', 'like', 'view_%');
        })->where('name', '!=', 'reports')->get();
        $accountant->syncPermissions($accountantPermissions);

        // Assign minimal permissions to User (only view_list and view)
        $clerkPermissions = Permission::where(function ($query) {
            $query->where('name', 'like', 'list_%')
                ->orWhere('name', 'like', 'view_%');
        })->where('name', '!=', 'reports')->get();
        $clerk->syncPermissions($clerkPermissions);

        // Assign only 'view_list_reports' permission for reports to all roles
        Permission::where('name', ' reports')->each(function ($permission) use ($admin, $accountant, $clerk) {
            $admin->givePermissionTo($permission);
            $accountant->givePermissionTo($permission);
            $clerk->givePermissionTo($permission);
        });
    }
}

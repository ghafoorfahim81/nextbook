<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $accountant = Role::query()->where('slug', 'accountant')->first();
        $clerk = Role::query()->where('slug', 'clerk')->first();

        if ($accountant) {
            $accountant->syncPermissions(
                Permission::query()->whereIn('name', [
                    'reports.view_any',
                    'reports.view',
                    'reports.export',
                    'purchases.view_any',
                    'purchases.view',
                    'purchases.create',
                    'purchases.update',
                    'purchases.approve',
                    'purchases.print',
                    'sales.view_any',
                    'sales.view',
                    'sales.create',
                    'sales.update',
                    'sales.approve',
                    'sales.print',
                    'receipts.view_any',
                    'receipts.view',
                    'receipts.create',
                    'payments.view_any',
                    'payments.view',
                    'payments.create',
                    'landed_costs.view_any',
                    'landed_costs.view',
                    'landed_costs.create',
                    'landed_costs.update',
                    'landed_costs.allocate',
                    'landed_costs.post',
                ])->get()
            );
        }

        if ($clerk) {
            $clerk->syncPermissions(
                Permission::query()->where(function ($query) {
                    $query->where('name', 'like', '%.view')
                        ->orWhere('name', 'like', '%.view_any');
                })->get()
            );
        }
    }

    public function down(): void
    {
        // Intentionally left blank because the previous permission set was invalid.
    }
};

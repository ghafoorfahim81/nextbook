<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Uid\Ulid;

return new class extends Migration
{
    /**
     * Customer groups and payment terms are new referenceable entities on the ledger
     * form. RolePermissionSeeder creates their permissions on a fresh install; this
     * migration back-fills them for databases that were already seeded.
     */
    public function up(): void
    {
        $actions = ['view_any', 'view', 'create', 'update', 'delete', 'restore', 'force_delete', 'export'];

        $names = [];

        foreach (['customer_groups', 'payment_terms'] as $resource) {
            foreach ($actions as $action) {
                $name = "{$resource}.{$action}";
                $names[] = $name;

                $existing = Permission::query()
                    ->where('name', $name)
                    ->where('guard_name', 'web')
                    ->first();

                if ($existing) {
                    if (! empty($existing->deleted_at)) {
                        $existing->forceFill(['deleted_at' => null])->save();
                    }

                    continue;
                }

                Permission::create([
                    'id' => (string) new Ulid(),
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
            }
        }

        $permissions = Permission::query()->whereIn('name', $names)->get();

        // Mirrors the seeder: super-admin and admin get the full set, clerk keeps
        // view-only access. Accountant has no administration permissions.
        foreach (['super-admin', 'admin'] as $slug) {
            Role::query()->where('slug', $slug)->first()?->givePermissionTo($permissions);
        }

        Role::query()->where('slug', 'clerk')->first()?->givePermissionTo(
            $permissions->filter(fn (Permission $permission) => str_ends_with($permission->name, '.view')
                || str_ends_with($permission->name, '.view_any'))
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where(function ($query) {
                $query->where('name', 'like', 'customer_groups.%')
                    ->orWhere('name', 'like', 'payment_terms.%');
            })
            ->get()
            ->each
            ->forceDelete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

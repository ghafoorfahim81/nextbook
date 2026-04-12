<?php

namespace Database\Seeders\UserManagement;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Uid\Ulid;

class AdditionalPermissionsSeeder extends Seeder
{
    /**
     * Additive permission seed list for production updates.
     *
     * Append new permissions here and run:
     * php artisan db:seed --class="Database\\Seeders\\UserManagement\\AdditionalPermissionsSeeder"
     *
     * This seeder only creates missing permissions and grants them to the
     * listed roles. It never syncs or removes existing permissions.
     *
     * @var array<string, array<int, string>>
     */
    private array $permissions = [
        'deleted_records.view_any' => ['super-admin', 'admin'],
        'deleted_records.view' => ['super-admin', 'admin'],
        'deleted_records.restore' => ['super-admin', 'admin'],
        'deleted_records.force_delete' => ['super-admin', 'admin'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permissionName => $roleNames) {
            $permission = $this->ensurePermission($permissionName);

            if ($roleNames === []) {
                continue;
            }

            Role::query()
                ->whereIn('name', $roleNames)
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ensurePermission(string $permissionName): Permission
    {
        $permission = Permission::query()
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->first();

        if ($permission) {
            if (!empty($permission->deleted_at)) {
                $permission->deleted_at = null;
                $permission->save();
            }

            return $permission;
        }

        return Permission::create([
            'id' => (string) new Ulid(),
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
    }
}

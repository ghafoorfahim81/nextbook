<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Uid\Ulid;

/**
 * Phase 7: cost_centers.* and allocation_rules.*. RolePermissionSeeder creates
 * them on a fresh install; this back-fills a database already seeded. Same
 * grants as the seeder: super-admin and admin everything, clerk view only.
 */
return new class extends Migration
{
    private const RESOURCES = ['cost_centers', 'allocation_rules'];

    private const ACTIONS = ['view_any', 'view', 'create', 'update', 'delete', 'import', 'export'];

    public function up(): void
    {
        $names = [];

        foreach (self::RESOURCES as $resource) {
            foreach (self::ACTIONS as $action) {
                $names[] = $name = "{$resource}.{$action}";

                Permission::query()->where('name', $name)->where('guard_name', 'web')->exists()
                    || Permission::create(['id' => (string) new Ulid(), 'name' => $name, 'guard_name' => 'web']);
            }
        }

        $permissions = Permission::query()->whereIn('name', $names)->get();

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
            ->where(fn ($query) => $query->where('name', 'like', 'cost_centers.%')->orWhere('name', 'like', 'allocation_rules.%'))
            ->get()
            ->each
            ->forceDelete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

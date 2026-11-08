<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Uid\Ulid;

/**
 * Reversing a landed cost gets its own permission instead of borrowing
 * landed_costs.post. RolePermissionSeeder creates it on a fresh install; this
 * back-fills a seeded database, handing it to every role and user who could
 * already reverse, so nobody loses the ability on deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $reverse = Permission::query()->where('name', 'landed_costs.reverse')->where('guard_name', 'web')->first()
            ?? Permission::create(['id' => (string) new Ulid(), 'name' => 'landed_costs.reverse', 'guard_name' => 'web']);

        $post = Permission::query()->where('name', 'landed_costs.post')->where('guard_name', 'web')->first();

        if ($post) {
            DB::statement(
                'INSERT INTO role_has_permissions (permission_id, role_id)
                 SELECT ?, role_id FROM role_has_permissions WHERE permission_id = ?
                 ON CONFLICT DO NOTHING',
                [$reverse->id, $post->id]
            );

            DB::statement(
                'INSERT INTO model_has_permissions (permission_id, model_type, model_id, team_id)
                 SELECT ?, model_type, model_id, team_id FROM model_has_permissions WHERE permission_id = ?
                 ON CONFLICT DO NOTHING',
                [$reverse->id, $post->id]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'landed_costs.reverse')->get()->each->forceDelete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

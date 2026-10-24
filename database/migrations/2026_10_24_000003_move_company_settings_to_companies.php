<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Uid\Ulid;

/**
 * Phase 6: settings that belong to the company move off the users.
 *
 * Until now every user held a full copy of every setting, invoice prefixes and
 * posting rules included, and that copy was what the application read. From
 * here the keys listed in config/preferences.php (company_keys) are read from
 * companies.preferences only.
 *
 * Each company takes them from its super-admin — the earliest, if it has
 * several — or, without one, from its earliest user. What companies.preferences
 * already holds (the 2026_08_05 copy, which the item form reads) is kept; the
 * user's values go over it. Users' own copies are left in place, unread.
 *
 * Also adds company_settings.update for super-admin and admin; the seeder does
 * the same on a fresh install.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()
            ->where('name', 'company_settings.update')
            ->where('guard_name', 'web')
            ->first()
            ?? Permission::create([
                'id' => (string) new Ulid(),
                'name' => 'company_settings.update',
                'guard_name' => 'web',
            ]);

        foreach (['super-admin', 'admin'] as $slug) {
            Role::query()->where('slug', $slug)->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdminRoleId = DB::table('roles')->where('slug', 'super-admin')->value('id');

        foreach (DB::table('companies')->get(['id', 'preferences']) as $company) {
            $source = DB::table('users')
                ->join('model_has_roles', function ($join) use ($company, $superAdminRoleId) {
                    $join->on('model_has_roles.model_id', '=', 'users.id')
                        ->where('model_has_roles.model_type', (new \App\Models\User)->getMorphClass())
                        ->where('model_has_roles.role_id', $superAdminRoleId)
                        ->where('model_has_roles.team_id', $company->id);
                })
                ->whereNotNull('users.preferences')
                ->orderBy('users.created_at')
                ->value('users.preferences')
                ?? DB::table('users')
                    ->where('company_id', $company->id)
                    ->whereNotNull('preferences')
                    ->orderBy('created_at')
                    ->value('preferences');

            $values = Settings::companyPart((array) json_decode((string) $source, true));

            if ($values === []) {
                continue;
            }

            $existing = (array) json_decode((string) $company->preferences, true);

            DB::table('companies')->where('id', $company->id)->update([
                'preferences' => json_encode(array_replace_recursive($existing, $values)),
            ]);
        }
    }

    public function down(): void
    {
        // The settings stay where they were copied; the users' copies were
        // never removed, so rolling the code back reads those again.
        Permission::query()->where('name', 'company_settings.update')->get()->each->forceDelete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

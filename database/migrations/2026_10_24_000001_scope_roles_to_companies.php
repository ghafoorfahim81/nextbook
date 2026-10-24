<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6: roles are assigned per company (Spatie "teams", team_id = company).
 *
 * Role definitions stay one shared set: roles.team_id is added because Spatie
 * reads it, and stays null on every seeded role. What becomes per company is
 * who holds which role — model_has_roles and model_has_permissions gain
 * team_id, part of their primary key.
 *
 * Existing assignments are copied into every company the user belongs to (the
 * home company and each granted one), so nobody gains or loses anything on the
 * day this runs; admins change them per company afterwards. An assignment of a
 * user with no company at all has nowhere to go and is dropped.
 *
 * The column is team_id rather than company_id on purpose: a company_id column
 * added after the row level security migration would get no policy, and these
 * rows are only ever read through the active company anyway (the team resolver
 * is CompanyContext).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->ulid('team_id')->nullable()->after('id');
            $table->index('team_id', 'roles_team_foreign_key_index');
        });

        foreach (['model_has_roles' => 'role_id', 'model_has_permissions' => 'permission_id'] as $pivot => $key) {
            Schema::table($pivot, function (Blueprint $table) {
                $table->ulid('team_id')->nullable();
            });

            // The old key has no team in it, so the per-company copies below
            // would collide with it.
            DB::statement("ALTER TABLE {$pivot} DROP CONSTRAINT {$pivot}_pkey");

            // One copy per company the user belongs to: home, then each grant.
            DB::statement("
                INSERT INTO {$pivot} ({$key}, model_type, model_id, team_id)
                SELECT p.{$key}, p.model_type, p.model_id, memberships.company_id
                FROM {$pivot} AS p
                JOIN (
                    SELECT u.id AS user_id, u.company_id FROM users AS u WHERE u.company_id IS NOT NULL
                    UNION
                    SELECT a.user_id, a.company_id FROM user_company_access AS a
                ) AS memberships ON memberships.user_id = p.model_id
                WHERE p.team_id IS NULL
                  AND p.model_type IN ('App\\Models\\User', 'user')
            ");

            DB::table($pivot)->whereNull('team_id')->delete();

            DB::statement("ALTER TABLE {$pivot} ALTER COLUMN team_id SET NOT NULL");
            DB::statement("ALTER TABLE {$pivot} ADD PRIMARY KEY (team_id, {$key}, model_id, model_type)");
            DB::statement("CREATE INDEX {$pivot}_team_foreign_key_index ON {$pivot} (team_id)");
            DB::statement("ALTER TABLE {$pivot} ADD CONSTRAINT {$pivot}_team_id_foreign FOREIGN KEY (team_id) REFERENCES companies (id) ON DELETE CASCADE");
        }
    }

    public function down(): void
    {
        foreach (['model_has_roles' => 'role_id', 'model_has_permissions' => 'permission_id'] as $pivot => $key) {
            DB::statement("ALTER TABLE {$pivot} DROP CONSTRAINT {$pivot}_team_id_foreign");
            DB::statement("ALTER TABLE {$pivot} DROP CONSTRAINT {$pivot}_pkey");
            // Collapse the per-company copies back to one assignment each.
            DB::statement("
                DELETE FROM {$pivot} AS a USING {$pivot} AS b
                WHERE a.{$key} = b.{$key} AND a.model_id = b.model_id AND a.model_type = b.model_type
                  AND a.team_id > b.team_id
            ");
            Schema::table($pivot, fn (Blueprint $table) => $table->dropColumn('team_id'));
            DB::statement("ALTER TABLE {$pivot} ADD PRIMARY KEY ({$key}, model_id, model_type)");
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex('roles_team_foreign_key_index');
            $table->dropColumn('team_id');
        });
    }
};

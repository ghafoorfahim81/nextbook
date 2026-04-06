<?php

namespace Tests\Feature\UserManagement;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class RoleManagementFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    public function test_role_show_returns_permissions_and_assigned_users(): void
    {
        $ctx = $this->bootstrapErpContext();

        $permission = Permission::create([
            'id' => (string) new Ulid(),
            'name' => 'roles.view',
            'guard_name' => 'web',
        ]);

        $role = Role::create([
            'name' => 'manager',
            'slug' => 'manager',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);

        $assignedUser = User::factory()->create([
            'branch_id' => $ctx['branch']->id,
            'company_id' => $ctx['company']->id,
            'preferences' => User::DEFAULT_PREFERENCES,
        ]);
        $assignedUser->assignRole($role);

        $this->getJson(route('roles.show', $role))
            ->assertOk()
            ->assertJsonPath('data.name', 'manager')
            ->assertJsonPath('data.permissions.0.name', 'roles.view')
            ->assertJsonPath('data.users.0.id', $assignedUser->id);
    }

    public function test_role_update_redirects_successfully_for_assigned_roles(): void
    {
        $ctx = $this->bootstrapErpContext();

        $permission = Permission::create([
            'id' => (string) new Ulid(),
            'name' => 'roles.update',
            'guard_name' => 'web',
        ]);

        $role = Role::create([
            'name' => 'supervisor',
            'slug' => 'supervisor',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);

        $assignedUser = User::factory()->create([
            'branch_id' => $ctx['branch']->id,
            'company_id' => $ctx['company']->id,
            'preferences' => User::DEFAULT_PREFERENCES,
        ]);
        $assignedUser->assignRole($role);

        $this->patch(route('roles.update', $role), [
            'name' => 'supervisor-updated',
            'permissions' => [$permission->id],
        ])->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'supervisor-updated',
        ]);
    }
}

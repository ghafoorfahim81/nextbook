<?php

namespace Tests\Feature\UserManagement;

use App\Models\Administration\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\Uid\Ulid;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Authorization around the user management screens.
 *
 * `authorizeResource` only covers the seven RESTful actions, so the restore and
 * force-delete endpoints have to gate themselves, and route-model binding must
 * not hand one company's admin an account belonging to another tenant.
 */
class UserManagementAuthorizationTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    /** A signed-in user of the same company holding exactly the given permissions. */
    private function userWith(array $permissions = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'limited-'.count($permissions).'-'.md5(implode(',', $permissions)), 'guard_name' => 'web'],
            ['slug' => 'limited-'.md5(implode(',', $permissions))],
        );

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['id' => (string) new Ulid()],
            ));
        }

        return tap(User::factory()->create([
            'preferences' => User::DEFAULT_PREFERENCES,
            'company_id' => $this->ctx['company']->id,
            'branch_id' => $this->ctx['branch']->id,
        ]), fn (User $user) => $user->assignRole($role));
    }

    private function trashedUser(): User
    {
        $user = User::factory()->create([
            'preferences' => User::DEFAULT_PREFERENCES,
            'company_id' => $this->ctx['company']->id,
            'branch_id' => $this->ctx['branch']->id,
        ]);
        $user->delete();

        return $user;
    }

    public function test_restoring_a_user_requires_the_delete_permission(): void
    {
        $trashed = $this->trashedUser();

        $this->actingAs($this->userWith(['users.view_any', 'users.update']))
            ->patch(route('users.restore', $trashed->id))
            ->assertForbidden();

        $this->assertSoftDeleted('users', ['id' => $trashed->id]);
    }

    public function test_a_user_holding_the_delete_permission_can_restore(): void
    {
        $trashed = $this->trashedUser();

        $this->actingAs($this->userWith(['users.delete']))
            ->patch(route('users.restore', $trashed->id))
            ->assertRedirect(route('users.index'));

        $this->assertNotSoftDeleted('users', ['id' => $trashed->id]);
    }

    public function test_force_deleting_a_user_requires_the_delete_permission(): void
    {
        $trashed = $this->trashedUser();

        $this->actingAs($this->userWith(['users.view_any', 'users.update']))
            ->delete(route('users.force-delete', $trashed->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $trashed->id]);
    }

    public function test_a_user_cannot_force_delete_their_own_account(): void
    {
        $actor = $this->userWith(['users.delete']);

        $this->actingAs($actor)
            ->delete(route('users.force-delete', $actor->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $actor->id]);
    }

    public function test_an_account_from_another_company_is_out_of_reach(): void
    {
        $otherCompany = Company::factory()->create([
            'name_en' => 'Other Tenant',
            'currency_id' => $this->ctx['currency']->id,
        ]);

        $outsider = User::factory()->create([
            'preferences' => User::DEFAULT_PREFERENCES,
            'company_id' => $otherCompany->id,
            'branch_id' => $this->ctx['branch']->id,
        ]);

        $actor = $this->userWith(['users.view', 'users.update', 'users.delete']);

        $this->actingAs($actor)->get(route('users.edit', $outsider))->assertForbidden();
        $this->actingAs($actor)->delete(route('users.destroy', $outsider))->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $outsider->id]);
    }

    public function test_the_user_list_is_limited_to_the_acting_company(): void
    {
        $otherCompany = Company::factory()->create([
            'name_en' => 'Other Tenant',
            'currency_id' => $this->ctx['currency']->id,
        ]);

        $outsider = User::factory()->create([
            'preferences' => User::DEFAULT_PREFERENCES,
            'company_id' => $otherCompany->id,
            'branch_id' => $this->ctx['branch']->id,
        ]);

        $actor = $this->userWith(['users.view_any']);

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('UserManagement/Users/Index')
                ->where('users.data', fn ($users) => collect($users)
                    ->pluck('id')
                    ->doesntContain($outsider->id)));
    }

    public function test_restoring_a_role_requires_the_delete_permission(): void
    {
        $role = Role::create(['name' => 'archived-role', 'slug' => 'archived-role', 'guard_name' => 'web']);
        $role->delete();

        $this->actingAs($this->userWith(['roles.view_any', 'roles.update']))
            ->patch(route('roles.restore', $role->id))
            ->assertForbidden();

        $this->assertSoftDeleted('roles', ['id' => $role->id]);

        $this->actingAs($this->userWith(['roles.delete']))
            ->patch(route('roles.restore', $role->id))
            ->assertRedirect(route('roles.index'));

        $this->assertNotSoftDeleted('roles', ['id' => $role->id]);
    }
}

<?php

use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Administration\UserBranchAccess;
use App\Models\Administration\UserCompanyAccess;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAccessService;
use App\Support\CompanyDatabaseContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The access screen (phase 5): a super-admin chooses which of their companies,
 * and which branches in them, a user may act in.
 */
function screenCompany(string $name): array
{
    $company = Company::factory()->create(['name_en' => $name]);
    $main = CompanyDatabaseContext::runFor($company->id, fn () => Branch::factory()->create([
        'company_id' => $company->id, 'name' => $name.' Main', 'is_main' => true,
    ]));

    return [$company, $main];
}

function screenBranch(Company $company, string $name): Branch
{
    return CompanyDatabaseContext::runFor($company->id, fn () => Branch::factory()->create([
        'company_id' => $company->id, 'name' => $name,
    ]));
}

function screenUser(Company $company, Branch $branch, bool $superAdmin = false): User
{
    $user = CompanyDatabaseContext::runFor($company->id, fn () => User::factory()->create([
        'company_id' => $company->id, 'branch_id' => $branch->id,
    ]));

    if ($superAdmin) {
        $user->assignRole(Role::query()->firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web'], ['slug' => 'super-admin']));
    }

    return $user->refresh();
}

it('shows a super-admin only the companies they belong to', function () {
    [$electronics, $kabul] = screenCompany('Ahmad Electronics');
    [$construction] = screenCompany('Ahmad Construction');
    screenCompany('Somebody Else');

    $admin = screenUser($electronics, $kabul, superAdmin: true);
    app(UserAccessService::class)->grantCompany($admin, $construction->id);
    $clerk = screenUser($electronics, $kabul);

    $this->actingAs($admin)->get(route('users.access.edit', $clerk))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('UserManagement/Users/Access')
            ->has('companies', 2)
            ->where('companies.0.id', $electronics->id)
            ->where('companies.0.is_home', true)
            ->where('granted.companies', [$electronics->id]));
});

it('saves the companies and branches ticked, and drops a branch with its company', function () {
    [$electronics, $kabul] = screenCompany('Ahmad Electronics');
    $herat = screenBranch($electronics, 'Herat');
    [$construction, $constructionMain] = screenCompany('Ahmad Construction');

    $admin = screenUser($electronics, $kabul, superAdmin: true);
    app(UserAccessService::class)->grantCompany($admin, $construction->id);
    $clerk = screenUser($electronics, $kabul);

    $this->actingAs($admin)->put(route('users.access.update', $clerk), [
        'companies' => [$construction->id],
        'branches' => [$herat->id, $constructionMain->id],
    ])->assertSessionHasNoErrors();

    $access = app(UserAccessService::class);
    expect($access->allowedBranchIds($clerk, $electronics->id))->toEqualCanonicalizing([$kabul->id, $herat->id])
        ->and($access->allowedBranchIds($clerk, $construction->id))->toBe([$constructionMain->id]);

    // Unticking Construction takes its branch with it; the home company stays.
    $this->put(route('users.access.update', $clerk), [
        'companies' => [],
        'branches' => [$herat->id, $constructionMain->id],
    ])->assertSessionHasNoErrors();

    expect($access->canAccessCompany($clerk, $construction->id))->toBeFalse()
        ->and(UserBranchAccess::query()->where('branch_id', $constructionMain->id)->exists())->toBeFalse()
        ->and($access->canAccessCompany($clerk, $electronics->id))->toBeTrue()
        ->and($access->allowedBranchIds($clerk, $electronics->id))->toContain($herat->id);
});

it('leaves grants in companies the super-admin cannot see alone', function () {
    [$electronics, $kabul] = screenCompany('Ahmad Electronics');
    [$elsewhere, $elsewhereMain] = screenCompany('Somebody Else');

    $admin = screenUser($electronics, $kabul, superAdmin: true);
    $clerk = screenUser($electronics, $kabul);

    // Given by someone in that other company.
    app(UserAccessService::class)->grantBranch($clerk, $elsewhereMain);

    $this->actingAs($admin)->put(route('users.access.update', $clerk), [
        'companies' => [$elsewhere->id],
        'branches' => [],
    ])->assertSessionHasNoErrors();

    expect(UserCompanyAccess::query()->where('user_id', $clerk->id)->where('company_id', $elsewhere->id)->exists())->toBeTrue()
        ->and(UserBranchAccess::query()->where('user_id', $clerk->id)->where('branch_id', $elsewhereMain->id)->exists())->toBeTrue();
});

it('is for super-admins only, and only for users of the company', function () {
    [$electronics, $kabul] = screenCompany('Ahmad Electronics');
    [$elsewhere, $elsewhereMain] = screenCompany('Somebody Else');

    $clerk = screenUser($electronics, $kabul);
    $colleague = screenUser($electronics, $kabul);
    $outsider = screenUser($elsewhere, $elsewhereMain);
    $admin = screenUser($electronics, $kabul, superAdmin: true);

    $this->actingAs($clerk)->get(route('users.access.edit', $colleague))->assertForbidden();
    $this->actingAs($admin)->get(route('users.access.edit', $outsider))->assertNotFound();
    $this->put(route('users.access.update', $outsider), ['companies' => [$electronics->id]])->assertNotFound();

    expect(UserCompanyAccess::query()->where('user_id', $outsider->id)->exists())->toBeFalse();
});

<?php

use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Administration\UserBranchAccess;
use App\Models\Administration\UserCompanyAccess;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAccessService;
use App\Support\CompanyDatabaseContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Phase 5: a user acting in more than one company, and in some branches of it.
 *
 * Each company's fixtures are built with the connection acting for that
 * company, as in CompanyScopeTest: creating them is audited, and the row level
 * security policies take an activity log row only from a connection acting for
 * the company it names.
 */
function accessCompany(string $name): array
{
    $company = Company::factory()->create(['name_en' => $name]);

    $main = CompanyDatabaseContext::runFor($company->id, fn () => Branch::factory()->create([
        'company_id' => $company->id,
        'name' => $name.' Main',
        'is_main' => true,
    ]));

    return [$company, $main];
}

function accessBranch(Company $company, string $name): Branch
{
    return CompanyDatabaseContext::runFor($company->id, fn () => Branch::factory()->create([
        'company_id' => $company->id,
        'name' => $name,
    ]));
}

function accessUser(Company $company, Branch $branch, bool $superAdmin = false): User
{
    $user = CompanyDatabaseContext::runFor($company->id, fn () => User::factory()->create([
        'email' => 'access-'.fake()->unique()->numberBetween(1000, 99999).'@example.test',
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]));

    if ($superAdmin) {
        $user->assignRole(Role::query()->firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'web'],
            ['slug' => 'super-admin']
        ));
    }

    return $user->refresh();
}

it('switches to a company the user has been given and lands in its main branch', function () {
    [$electronics, $kabul] = accessCompany('Ahmad Electronics');
    [$construction, $constructionMain] = accessCompany('Ahmad Construction');

    $user = accessUser($electronics, $kabul, superAdmin: true);
    app(UserAccessService::class)->grantCompany($user, $construction->id);

    $this->actingAs($user)
        ->post(route('companies.switch'), ['company_id' => $construction->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->get(route('dashboard'));

    expect(app('active_company_id'))->toBe($construction->id)
        ->and(app('accessible_company_ids'))->toBe([$construction->id])
        ->and(app('active_branch_id'))->toBe($constructionMain->id)
        ->and(CompanyDatabaseContext::current())->toBe($construction->id)
        // The home company stays where it was.
        ->and($user->refresh()->company_id)->toBe($electronics->id);
});

it('refuses a company the user has not been given', function () {
    [$electronics, $kabul] = accessCompany('Ahmad Electronics');
    [$construction] = accessCompany('Ahmad Construction');

    $user = accessUser($electronics, $kabul, superAdmin: true);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('companies.switch'), ['company_id' => $construction->id])
        ->assertSessionHasErrors('company_id');

    $this->get(route('dashboard'));

    expect(app('active_company_id'))->toBe($electronics->id);
});

it('falls back to the home company once access is withdrawn', function () {
    [$electronics, $kabul] = accessCompany('Ahmad Electronics');
    [$construction] = accessCompany('Ahmad Construction');

    $user = accessUser($electronics, $kabul, superAdmin: true);
    app(UserAccessService::class)->grantCompany($user, $construction->id);

    $this->actingAs($user)->post(route('companies.switch'), ['company_id' => $construction->id]);

    UserCompanyAccess::query()->where('user_id', $user->id)->delete();

    $this->get(route('dashboard'));

    expect(app('active_company_id'))->toBe($electronics->id)
        ->and(app('active_branch_id'))->toBe($kabul->id)
        ->and(session('active_company_id'))->toBeNull();
});

it('keeps a user who is not a super-admin to the branches granted', function () {
    [$company, $kabul] = accessCompany('Ahmad Electronics');
    $herat = accessBranch($company, 'Herat');
    $mazar = accessBranch($company, 'Mazar');

    $user = accessUser($company, $kabul);
    app(UserAccessService::class)->grantBranch($user, $herat);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('branches.switch'), ['branch_id' => $herat->id])
        ->assertSessionHasNoErrors();

    $this->get(route('dashboard'));
    expect(app('active_branch_id'))->toBe($herat->id);

    $this->from(route('dashboard'))
        ->post(route('branches.switch'), ['branch_id' => $mazar->id])
        ->assertSessionHasErrors('branch_id');

    $this->get(route('dashboard'));
    expect(app('active_branch_id'))->toBe($herat->id);
});

it('lets a super-admin act in every branch of a company they have', function () {
    [$company, $kabul] = accessCompany('Ahmad Electronics');
    $herat = accessBranch($company, 'Herat');

    $user = accessUser($company, $kabul, superAdmin: true);

    expect(app(UserAccessService::class)->allowedBranchIds($user, $company->id))
        ->toEqualCanonicalizing([$kabul->id, $herat->id]);
});

it('refuses to switch into a company where the user has no branch', function () {
    [$electronics, $kabul] = accessCompany('Ahmad Electronics');
    [$construction] = accessCompany('Ahmad Construction');

    // Not a super-admin, and given the company but none of its branches.
    $user = accessUser($electronics, $kabul);
    app(UserAccessService::class)->grantCompany($user, $construction->id);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('companies.switch'), ['company_id' => $construction->id])
        ->assertSessionHasErrors('company_id');
});

it('cannot grant a branch under a company it does not belong to', function () {
    [$electronics, $kabul] = accessCompany('Ahmad Electronics');
    [, $constructionMain] = accessCompany('Ahmad Construction');

    $user = accessUser($electronics, $kabul);
    app(UserAccessService::class)->grantCompany($user, $electronics->id);

    // The composite foreign key: the grant names Electronics, the branch is
    // Construction's.
    expect(fn () => UserBranchAccess::query()->create([
        'user_id' => $user->id,
        'company_id' => $electronics->id,
        'branch_id' => $constructionMain->id,
    ]))->toThrow(QueryException::class);
});

it('shares the branch the request acts in, not the home branch', function () {
    [$company, $kabul] = accessCompany('Ahmad Electronics');
    $herat = accessBranch($company, 'Herat');

    $user = accessUser($company, $kabul, superAdmin: true);

    $this->actingAs($user)->post(route('branches.switch'), ['branch_id' => $herat->id]);

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.user.branch_id', $herat->id));
});

/**
 * Step D: a page left open in one company must not save into another after
 * the session has switched. The page sends the company it was opened in.
 */
function twoCompanyUserSwitchedToSecond($test): array
{
    [$electronics, $kabul] = accessCompany('Ahmad Electronics');
    [$construction] = accessCompany('Ahmad Construction');

    $user = accessUser($electronics, $kabul, superAdmin: true);
    app(UserAccessService::class)->grantCompany($user, $construction->id);

    $test->actingAs($user)->post(route('companies.switch'), ['company_id' => $construction->id]);

    return [$electronics, $construction];
}

it('refuses a write sent from a page opened in another company', function () {
    [$electronics] = twoCompanyUserSwitchedToSecond($this);

    $this->withHeader('X-Nextbook-Company', $electronics->id)
        ->post(route('brands.store'), ['name' => 'Stale Tab Brand'])
        ->assertRedirect(route('home'))
        ->assertSessionHas('context_notice', __('general.company_changed_in_another_tab'));

    expect(\Illuminate\Support\Facades\DB::table('brands')->where('name', 'Stale Tab Brand')->exists())->toBeFalse();
});

it('accepts a write from a page of the company the session is in', function () {
    [, $construction] = twoCompanyUserSwitchedToSecond($this);

    $this->withHeader('X-Nextbook-Company', $construction->id)
        ->post(route('brands.store'), ['name' => 'Current Tab Brand'])
        ->assertSessionHasNoErrors();

    expect(\Illuminate\Support\Facades\DB::table('brands')
        ->where('name', 'Current Tab Brand')
        ->value('company_id'))->toBe($construction->id);
});

it('answers a stale write that expects JSON with a conflict', function () {
    [$electronics] = twoCompanyUserSwitchedToSecond($this);

    $this->withHeader('X-Nextbook-Company', $electronics->id)
        ->postJson(route('brands.store'), ['name' => 'Stale Json Brand'])
        ->assertStatus(409);
});

it('lets a page of the old company switch company', function () {
    [$electronics, $construction] = twoCompanyUserSwitchedToSecond($this);

    // The switcher on a tab still showing Electronics, opened before the switch
    // to Construction: its page company is stale, and switching is exactly
    // what it should be allowed to do.
    $this->withHeader('X-Nextbook-Company', $electronics->id)
        ->post(route('companies.switch'), ['company_id' => $electronics->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->get(route('dashboard'));
    expect(app('active_company_id'))->toBe($electronics->id);
});

/**
 * Step E: the top bar offers a switcher only when its list has a second entry,
 * so both lists are shared, and they hold only what the user may use.
 */
it('shares the companies and branches the switchers may offer', function () {
    [$electronics, $kabul] = accessCompany('Ahmad Electronics');
    $herat = accessBranch($electronics, 'Herat');
    [$construction] = accessCompany('Ahmad Construction');
    accessCompany('Somebody Else');

    $user = accessUser($electronics, $kabul);
    app(UserAccessService::class)->grantBranch($user, $herat);
    app(UserAccessService::class)->grantCompany($user, $construction->id);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('switcher.companies', [
                ['id' => $electronics->id, 'name' => 'Ahmad Electronics'],
                ['id' => $construction->id, 'name' => 'Ahmad Construction'],
            ])
            // Main branch first, then by name.
            ->where('switcher.branches', [
                ['id' => $kabul->id, 'name' => 'Ahmad Electronics Main'],
                ['id' => $herat->id, 'name' => 'Herat'],
            ]));
});

it('offers a single-company user nothing to switch to', function () {
    [$company, $kabul] = accessCompany('Ahmad Electronics');

    $this->actingAs(accessUser($company, $kabul))->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('switcher.companies', 1)
            ->has('switcher.branches', 1));
});

<?php

use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\CompanyDatabaseContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the company boundary at the two places a request can cross it: the
 * branch switcher, and the branch context the rest of the request is built on.
 *
 * These are acceptance rows from the architecture plan — a user of one company
 * must have no way to read or act on another company's data by handing the
 * server an id.
 */

/**
 * Both helpers below build their rows with the connection pointed at the
 * company they belong to.
 *
 * These tests exist to construct a state the application will not: two
 * companies, and a user of one reaching for the other. Creating either of them
 * is audited, and the activity log row carries the company of the thing being
 * created — which the row level security policies accept only from a connection
 * acting for that company. Each company's fixtures are therefore built the way
 * that company's own users would build them, which is also what makes the
 * crossing attempted afterwards a real one.
 */
function superAdminIn(Company $company, Branch $branch): User
{
    $user = CompanyDatabaseContext::runFor($company->id, fn () => User::factory()->create([
        'email' => 'owner-'.fake()->unique()->numberBetween(1000, 9999).'@example.test',
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]));

    $role = Role::query()->firstOrCreate(
        ['name' => 'super-admin', 'guard_name' => 'web'],
        ['slug' => 'super-admin']
    );

    assignRoleInHomeCompany($user, $role);

    return $user->refresh();
}

function companyWithBranch(string $name): array
{
    $company = Company::factory()->create(['name_en' => $name]);

    $branch = CompanyDatabaseContext::runFor($company->id, fn () => Branch::factory()->create([
        'company_id' => $company->id,
        'name' => $name.' Main',
        'is_main' => true,
    ]));

    return [$company, $branch];
}

it('refuses to switch into a branch of another company', function () {
    [$ownCompany, $ownBranch] = companyWithBranch('Ahmad Electronics');
    [, $foreignBranch] = companyWithBranch('Ahmad Construction');

    $user = superAdminIn($ownCompany, $ownBranch);

    $response = $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('branches.switch'), ['branch_id' => $foreignBranch->id]);

    $response->assertSessionHasErrors('branch_id');

    expect($user->refresh()->branch_id)->toBe($ownBranch->id);
});

it('allows switching between branches of the same company', function () {
    [$company, $mainBranch] = companyWithBranch('Ahmad Electronics');

    // In this company's context, like companyWithBranch above: creating a
    // branch is audited, and the policies take an activity log row only from a
    // connection acting for the company it names.
    $herat = CompanyDatabaseContext::runFor($company->id, fn () => Branch::factory()->create([
        'company_id' => $company->id,
        'name' => 'Herat',
    ]));

    $user = superAdminIn($company, $mainBranch);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('branches.switch'), ['branch_id' => $herat->id])
        ->assertSessionHasNoErrors();

    // Since phase 5 the switch lives in the session: the next request acts in
    // Herat, and the home branch on the user is left where it was.
    $this->get(route('dashboard'));

    expect(app('active_branch_id'))->toBe($herat->id)
        ->and($user->refresh()->branch_id)->toBe($mainBranch->id);
});

it('ignores a session branch that belongs to another company', function () {
    [$ownCompany, $ownBranch] = companyWithBranch('Ahmad Electronics');
    [, $foreignBranch] = companyWithBranch('Ahmad Construction');

    $user = superAdminIn($ownCompany, $ownBranch);

    // SetActiveBranch reads this for super-admins. A stale or tampered session
    // must not be able to carry the request into another company's books.
    $this->actingAs($user)
        ->withSession(['active_branch_id' => $foreignBranch->id])
        ->get(route('dashboard'));

    expect(app('active_branch_id'))->toBe($ownBranch->id);
});

it('resolves the acting company from the user, not the request', function () {
    [$company, $branch] = companyWithBranch('Ahmad Electronics');
    [$otherCompany] = companyWithBranch('Ahmad Construction');

    $user = superAdminIn($company, $branch);

    $this->actingAs($user)->get(route('dashboard', ['company_id' => $otherCompany->id]));

    expect(app('active_company_id'))->toBe($company->id)
        ->and(app('accessible_company_ids'))->toBe([$company->id]);
});

it('does not let one company read another company\'s branches', function () {
    [$ownCompany, $ownBranch] = companyWithBranch('Ahmad Electronics');
    [, $foreignBranch] = companyWithBranch('Ahmad Construction');

    $this->actingAs(superAdminIn($ownCompany, $ownBranch));

    // Branch lists, the main-branch lookup and the Branch::query() fallbacks
    // all go through this scope; without it they read across companies.
    expect(Branch::query()->pluck('id')->all())->toBe([$ownBranch->id])
        ->and(Branch::query()->where('is_main', true)->value('id'))->toBe($ownBranch->id)
        ->and(Branch::query()->find($foreignBranch->id))->toBeNull();
});

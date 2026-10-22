<?php

use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAccessService;
use App\Support\CompanyDatabaseContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The two admin screens that act on tables outside row level security —
 * companies and users — stay inside the signed-in user's companies.
 *
 * Neither table carries a company scope, and the super-admin bypass in
 * AuthServiceProvider answers before any policy runs, so before these checks a
 * super-admin of one company could read, change, delete or restore another
 * customer's company, and open, change, delete or purge its users, by id.
 */
function boundaryCompany(string $name): array
{
    $company = Company::factory()->create(['name_en' => $name]);

    $branch = CompanyDatabaseContext::runFor($company->id, fn () => Branch::factory()->create([
        'company_id' => $company->id,
        'name' => $name.' Main',
        'is_main' => true,
    ]));

    $admin = CompanyDatabaseContext::runFor($company->id, fn () => User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]));

    $admin->assignRole(Role::query()->firstOrCreate(
        ['name' => 'super-admin', 'guard_name' => 'web'],
        ['slug' => 'super-admin']
    ));

    return [$company, $branch, $admin->refresh()];
}

it('lists only the companies the user belongs to', function () {
    [$own, , $admin] = boundaryCompany('Ahmad Electronics');
    [$other] = boundaryCompany('Somebody Else');

    $this->actingAs($admin)->get(route('companies.index'))
        ->assertInertia(fn ($page) => $page
            ->where('companies.data.0.id', $own->id)
            ->has('companies.data', 1));
});

it('will not show, change, delete or restore another company', function () {
    [, , $admin] = boundaryCompany('Ahmad Electronics');
    [$other] = boundaryCompany('Somebody Else');

    $this->actingAs($admin);

    $this->getJson(route('companies.show', $other))->assertNotFound();
    $this->putJson(route('companies.update', $other), ['name_en' => 'Taken over'])->assertNotFound();
    $this->deleteJson(route('companies.destroy', $other))->assertNotFound();

    expect(Company::query()->find($other->id)?->name_en)->toBe('Somebody Else');

    $other->delete();
    $this->patch(route('companies.restore', $other))->assertNotFound();
    $this->patch(route('company.restore', $other))->assertNotFound();

    expect(Company::withTrashed()->find($other->id)->trashed())->toBeTrue();
});

it('will not show, change or delete another company\'s users', function () {
    [, , $admin] = boundaryCompany('Ahmad Electronics');
    [, , $outsider] = boundaryCompany('Somebody Else');

    $this->actingAs($admin);

    $this->get(route('users.index'))
        ->assertInertia(fn ($page) => $page->where(
            'users.data',
            fn ($users) => collect($users)->pluck('id')->doesntContain($outsider->id)
        ));

    $this->getJson(route('users.show', $outsider))->assertNotFound();
    $this->get(route('users.edit', $outsider))->assertNotFound();
    $this->put(route('users.update', $outsider), [
        'name' => 'Taken over',
        'email' => $outsider->email,
    ])->assertNotFound();
    $this->delete(route('users.destroy', $outsider))->assertNotFound();

    expect(User::query()->find($outsider->id)?->name)->not->toBe('Taken over');
});

it('lists a user granted the company alongside its own users', function () {
    [$own, , $admin] = boundaryCompany('Ahmad Electronics');
    [, , $guest] = boundaryCompany('Ahmad Construction');

    app(UserAccessService::class)->grantCompany($guest, $own->id);

    $this->actingAs($admin)->get(route('users.index'))
        ->assertInertia(fn ($page) => $page->where(
            'users.data',
            fn ($users) => collect($users)->pluck('id')->contains($guest->id)
        ));
});

<?php

use App\Models\Account\Account;
use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Administration\Currency;
use App\Models\Administration\Warehouse;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAccessService;
use App\Support\CompanyDatabaseContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Phase 5 F: a company comes into being ready to trade, whether a customer
 * signs up or a super-admin adds one beside theirs.
 *
 * Sign-up used to create the company row alone — no branch, nothing
 * provisioned, a base currency picked by id from a list that was empty for a
 * user with no company — and the founder had no role to do anything with it.
 */
function setupPayload(array $overrides = []): array
{
    return $overrides + [
        'name_en' => 'Karimi Pharmacy',
        'calendar_type' => 'jalali',
        'working_style' => 'normal',
        'business_type' => 'pharmacy',
        'locale' => 'fa',
        'base_currency' => 'AFN',
    ];
}

function superAdminRole(): Role
{
    return Role::query()->firstOrCreate(
        ['name' => 'super-admin', 'guard_name' => 'web'],
        ['slug' => 'super-admin']
    );
}

/** What the new company holds, read as its own users would read it. */
function companyHoldings(Company $company): array
{
    return CompanyDatabaseContext::runFor($company->id, fn () => [
        'branches' => Branch::query()->withoutGlobalScopes()->where('company_id', $company->id)->get(),
        'accounts' => Account::query()->withoutGlobalScopes()->where('company_id', $company->id)->count(),
        'bases' => Currency::query()->withoutGlobalScopes()->where('company_id', $company->id)
            ->where('is_base_currency', true)->get(),
        'warehouses' => Warehouse::query()->withoutGlobalScopes()->where('company_id', $company->id)->count(),
    ]);
}

it('lets a new user found a company that is ready to trade', function () {
    superAdminRole();
    $founder = User::factory()->create(['company_id' => null, 'branch_id' => null]);

    $this->actingAs($founder)
        ->post(route('company.store'), setupPayload(['base_currency' => 'EUR']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $founder->refresh();
    $company = Company::query()->where('name_en', 'Karimi Pharmacy')->firstOrFail();
    $holdings = companyHoldings($company);

    expect($founder->company_id)->toBe($company->id)
        ->and($holdings['branches'])->toHaveCount(1)
        ->and($holdings['branches']->first()->is_main)->toBeTrue()
        ->and($founder->branch_id)->toBe($holdings['branches']->first()->id)
        // Provisioned: a chart of accounts and the branch's warehouse.
        ->and($holdings['accounts'])->toBeGreaterThan(0)
        ->and($holdings['warehouses'])->toBeGreaterThan(0)
        // EUR is not one of the currencies provisioning creates; it is added.
        ->and($holdings['bases'])->toHaveCount(1)
        ->and($holdings['bases']->first()->code)->toBe('EUR')
        ->and((float) $holdings['bases']->first()->exchange_rate)->toBe(1.0)
        ->and($founder->isSuperAdmin())->toBeTrue()
        // Its own calendar, not the creator's (who had none).
        ->and($company->calendar_type->value)->toBe('jalali');

    $this->get(route('dashboard'));
    expect(app('active_company_id'))->toBe($company->id);
});

it('does not let a user who has a company found another through sign-up', function () {
    $company = Company::factory()->create();
    $branch = CompanyDatabaseContext::runFor($company->id, fn () => Branch::factory()->create(['company_id' => $company->id]));
    $user = CompanyDatabaseContext::runFor($company->id, fn () => User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]));

    $this->actingAs($user)
        ->post(route('company.store'), setupPayload())
        ->assertForbidden();
});

it('lets a super-admin add a company, granted to them, home unchanged', function () {
    $home = Company::factory()->create();
    $branch = CompanyDatabaseContext::runFor($home->id, fn () => Branch::factory()->create([
        'company_id' => $home->id,
        'is_main' => true,
    ]));
    $admin = CompanyDatabaseContext::runFor($home->id, fn () => User::factory()->create([
        'company_id' => $home->id,
        'branch_id' => $branch->id,
    ]));
    assignRoleInHomeCompany($admin, superAdminRole());

    $this->actingAs($admin)
        ->post(route('companies.store'), setupPayload(['name_en' => 'Karimi Construction', 'business_type' => 'supermarket']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('companies.index'));

    $added = Company::query()->where('name_en', 'Karimi Construction')->firstOrFail();

    expect($admin->refresh()->company_id)->toBe($home->id)
        ->and(app(UserAccessService::class)->canAccessCompany($admin, $added->id))->toBeTrue()
        ->and(companyHoldings($added)['accounts'])->toBeGreaterThan(0);

    $this->post(route('companies.switch'), ['company_id' => $added->id])
        ->assertSessionHasNoErrors();
});

it('does not let anyone but a super-admin add a company', function () {
    $home = Company::factory()->create();
    $branch = CompanyDatabaseContext::runFor($home->id, fn () => Branch::factory()->create(['company_id' => $home->id]));
    $user = CompanyDatabaseContext::runFor($home->id, fn () => User::factory()->create([
        'company_id' => $home->id,
        'branch_id' => $branch->id,
    ]));

    $this->actingAs($user)
        ->post(route('companies.store'), setupPayload(['name_en' => 'Not Allowed']))
        ->assertForbidden();

    expect(Company::query()->where('name_en', 'Not Allowed')->exists())->toBeFalse();
});

it('answers the set-up screen with what was created, step by step', function () {
    superAdminRole();
    $founder = User::factory()->create(['company_id' => null, 'branch_id' => null]);

    $response = $this->actingAs($founder)
        ->postJson(route('company.store'), setupPayload())
        ->assertCreated()
        ->assertJsonPath('redirect', route('dashboard'));

    $summary = $response->json('summary');

    // The same steps, in the same order, as the screen lists them.
    expect(array_keys($summary))->toBe(array_keys(\App\Services\CompanySetupService::SUMMARY_STEPS))
        ->and($summary['accounts'])->toBeGreaterThan(0)
        ->and($summary['currencies'])->toBeGreaterThan(0)
        ->and($summary['warehouse'])->toBe(1)
        ->and($summary['company'])->toBe(1);
});

it('answers a set-up screen that left out a required field with the errors', function () {
    $founder = User::factory()->create(['company_id' => null, 'branch_id' => null]);

    $this->actingAs($founder)
        ->postJson(route('company.store'), setupPayload(['name_en' => '', 'base_currency' => 'XXX']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name_en', 'base_currency']);

    expect(Company::query()->count())->toBe(0);
});

/**
 * The wizard checks each step with the server before moving on, and the
 * whole form before the set-up screen opens. A precognitive request validates
 * and stops: it must never create anything.
 */
it('checks one step of the wizard without creating anything', function () {
    $founder = User::factory()->create(['company_id' => null, 'branch_id' => null]);

    $this->actingAs($founder)
        ->withHeaders(['Precognition' => 'true', 'Precognition-Validate-Only' => 'name_en,business_type'])
        ->postJson(route('company.store'), ['base_currency' => 'not a currency'])
        ->assertUnprocessable()
        // Only the fields of the step being checked.
        ->assertJsonValidationErrors(['name_en', 'business_type'])
        ->assertJsonMissingValidationErrors(['base_currency']);

    expect(Company::query()->count())->toBe(0);
});

it('passes the whole form through the check before the set-up screen opens', function () {
    $founder = User::factory()->create(['company_id' => null, 'branch_id' => null]);

    $this->actingAs($founder)
        ->withHeaders(['Precognition' => 'true'])
        ->postJson(route('company.store'), setupPayload())
        ->assertNoContent();

    expect(Company::query()->count())->toBe(0)
        ->and($founder->refresh()->company_id)->toBeNull();
});

it('stores the licence, its dates in the Gregorian calendar', function () {
    superAdminRole();
    $founder = User::factory()->create(['company_id' => null, 'branch_id' => null]);

    $this->actingAs($founder)
        ->postJson(route('company.store'), setupPayload([
            'license_number' => 'AISA-12345',
            // Picked on a Solar Hijri calendar.
            'license_issued_at' => '1405-01-01',
            'license_expires_at' => '1406-01-01',
        ]))
        ->assertCreated();

    $company = Company::query()->firstOrFail();

    expect($company->license_number)->toBe('AISA-12345')
        ->and(\Illuminate\Support\Carbon::parse($company->license_issued_at)->format('Y-m-d'))->toBe('2026-03-21')
        ->and($company->currency_id)->not->toBeNull();
});

it('makes the creator super-admin of the company they made, and only there', function () {
    superAdminRole();
    $founder = User::factory()->create(['company_id' => null, 'branch_id' => null]);

    $this->actingAs($founder)->postJson(route('company.store'), setupPayload())->assertCreated();

    $company = Company::query()->firstOrFail();
    $other = Company::factory()->create();
    $access = app(UserAccessService::class);

    expect($access->isSuperAdminIn($founder->fresh(), $company->id))->toBeTrue()
        ->and($access->isSuperAdminIn($founder->fresh(), $other->id))->toBeFalse();
});

<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Give a user a role in their home company (phase 6: roles are held per
 * company, so an assignment has to name one). Puts the team back afterwards,
 * so the rest of the test answers for the company it acts in.
 */
function assignRoleInHomeCompany(\App\Models\User $user, mixed $role): void
{
    assignRoleInCompany($user, $role, (string) $user->company_id);
}

/**
 * Give a user a role in one company: since phase 6 super-admin in the home
 * company says nothing about a company granted beside it.
 */
function assignRoleInCompany(\App\Models\User $user, mixed $role, string $companyId): void
{
    setPermissionsTeamId($companyId);

    try {
        $user->unsetRelation('roles')->assignRole($role);
    } finally {
        setPermissionsTeamId(null);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }
}

<?php

namespace App\Support\Inertia;

use Illuminate\Http\Request;

/**
 * Helper to forget Inertia shared data cache keys.
 *
 * Use after create/update/delete operations that affect lookup or domain data.
 *
 * LookupShared keys: main_branch, categories, branches, currencies, stores,
 * brands, unit_measures, sizes, home_currency, gl_accounts
 *
 * DomainShared keys: accounts, account_types, ledgers, items, roles
 *
 * CoreShared (user-specific): permissions, roles, role_slugs, preferences,
 * branch_name:{branchId}
 */
final class CacheForget
{
    public static function lookup(Request $request, string $name): void
    {
        \Illuminate\Support\Facades\Cache::forget(
            CacheKey::forCompanyBranchLocale($request, $name)
        );
    }

    public static function user(Request $request, string $name): void
    {
        \Illuminate\Support\Facades\Cache::forget(
            CacheKey::forUser($request, $name)
        );
    }
}

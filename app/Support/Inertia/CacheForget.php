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
    private const USER_AUTH_KEYS = [
        'permissions',
        'roles',
        'role_slugs',
    ];

    public static function lookup(Request $request, string $name): void
    {
        \Illuminate\Support\Facades\Cache::forget(
            CacheKey::forCompanyBranchLocale($request, $name)
        );
    }

    /**
     * Forget a lookup key in every branch context and locale.
     *
     * Lookup keys carry the acting branch and locale, so clearing only the current
     * one leaves the same data stale for users sitting in another branch or reading
     * the app in another language. Used for data that is shared across branches —
     * the branch list behind the switcher, for instance.
     */
    public static function lookupEverywhere(Request $request, string $name): void
    {
        $companyId = CacheKey::companyId($request) ?? 'none';

        $branchIds = \App\Models\Administration\Branch::query()
            ->withTrashed()
            ->pluck('id')
            ->push('none');

        foreach ($branchIds as $branchId) {
            foreach (\App\Enums\Locale::values() as $locale) {
                \Illuminate\Support\Facades\Cache::forget(
                    CacheKey::build($companyId, (string) $branchId, $locale, $name)
                );
            }
        }
    }

    public static function user(Request $request, string $name): void
    {
        \Illuminate\Support\Facades\Cache::forget(
            CacheKey::forUser($request, $name)
        );
    }

    public static function userById(string $userId, string $name): void
    {
        \Illuminate\Support\Facades\Cache::forget("inertia:user:{$userId}:{$name}");
    }

    public static function authForUserId(?string $userId): void
    {
        if (!$userId) {
            return;
        }

        foreach (self::USER_AUTH_KEYS as $name) {
            self::userById($userId, $name);
        }
    }
}

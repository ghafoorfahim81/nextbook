<?php

namespace App\Support;

use App\Models\Administration\Company;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Resolves a setting from the layer that owns it (phase 6).
 *
 * config/preferences.php lists which dot paths belong to the company; the rest
 * belong to the user. A company path resolves defaults → business profile →
 * company.preferences, and no user can override it. A user path resolves
 * defaults → user.preferences, as it always has (User::getAllPreferences).
 *
 * Nothing is memoised: every layer is an array already in memory, and a
 * request that changes a setting reads the new value straight back.
 */
class Settings
{
    public static function defaults(): array
    {
        return (array) config('preferences.defaults', []);
    }

    /** @return list<string> */
    public static function companyKeys(): array
    {
        return (array) config('preferences.company_keys', []);
    }

    /**
     * Whether a dot path is the company's: it is a company key or lies under
     * one ('transaction.sale_post_immediately' under 'transaction').
     */
    public static function isCompanyKey(string $path): bool
    {
        foreach (self::companyKeys() as $key) {
            if ($path === $key || str_starts_with($path, $key.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every company key resolved for one company, as a nested array holding
     * only those keys.
     */
    public static function forCompany(?Company $company): array
    {
        $defaults = self::defaults();
        $profile = (array) config('business_profiles.profiles.'.($company?->business_type?->value ?? '').'.settings', []);
        $stored = (array) ($company?->preferences ?? []);

        $resolved = [];

        foreach (self::companyKeys() as $key) {
            $value = Arr::get($defaults, $key);

            foreach ([$profile, $stored] as $layer) {
                if (! Arr::has($layer, $key)) {
                    continue;
                }

                $over = Arr::get($layer, $key);
                $value = is_array($value) && is_array($over)
                    ? array_replace_recursive($value, $over)
                    : $over;
            }

            Arr::set($resolved, $key, $value);
        }

        return $resolved;
    }

    /**
     * The full settings tree a user works with in a company: their own
     * preferences, with every company key taken from the company.
     */
    public static function resolve(?User $user = null, ?Company $company = null): array
    {
        $user ??= auth()->user();
        $company ??= CompanyContext::company();

        $tree = $user ? $user->getAllPreferences() : self::defaults();

        return self::overlayCompany($tree, $company);
    }

    /** Put a company's values over a user's tree (CoreShared caches the tree). */
    public static function overlayCompany(array $tree, ?Company $company): array
    {
        $resolved = self::forCompany($company);

        foreach (self::companyKeys() as $key) {
            Arr::set($tree, $key, Arr::get($resolved, $key));
        }

        return $tree;
    }

    public static function get(string $key, mixed $default = null, ?User $user = null): mixed
    {
        return data_get(self::resolve($user), $key, $default);
    }

    /** Only the company keys present in a (partial) settings array. */
    public static function companyPart(array $values): array
    {
        $part = [];

        foreach (self::companyKeys() as $key) {
            if (Arr::has($values, $key)) {
                Arr::set($part, $key, Arr::get($values, $key));
            }
        }

        return $part;
    }

    /** A (partial) settings array with every company key taken out. */
    public static function withoutCompanyKeys(array $values): array
    {
        $touched = [];

        foreach (self::companyKeys() as $key) {
            if (Arr::has($values, $key)) {
                Arr::forget($values, $key);
                $touched[] = explode('.', $key)[0];
            }
        }

        // A group the removal left empty held only company keys; drop it so
        // it does not overwrite the user's stored group with nothing.
        foreach (array_unique($touched) as $group) {
            if (($values[$group] ?? null) === []) {
                unset($values[$group]);
            }
        }

        return $values;
    }

    /** Merge company values into a company's stored settings and save. */
    public static function putForCompany(Company $company, array $values): void
    {
        $values = self::companyPart($values);

        if ($values === []) {
            return;
        }

        $company->preferences = array_replace_recursive((array) ($company->preferences ?? []), $values);
        $company->save();
    }
}

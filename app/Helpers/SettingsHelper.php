<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
if (!function_exists('user_preference')) {
    /**
     * Get a user preference value.
     *
     * @param string $key Dot notation key (e.g., 'appearance.theme')
     * @param mixed $default Default value if not found
     * @param User|null $user User instance (defaults to authenticated user)
     * @return mixed
     */
    function user_preference(string $key, mixed $default = null, ?User $user = null): mixed
    {
        // A company key (config/preferences.php) comes from the active
        // company, whatever the user has stored.
        return \App\Support\Settings::get($key, $default, $user);
    }
}

if (!function_exists('set_company_setting')) {
    /**
     * Set a company setting (a key listed in config/preferences.php).
     *
     * @param string $key Dot notation key (e.g., 'transaction.sale_post_immediately')
     * @param mixed $value Value to set
     * @param \App\Models\Administration\Company|null $company Defaults to the active company
     */
    function set_company_setting(string $key, mixed $value, ?\App\Models\Administration\Company $company = null): void
    {
        $company = $company ?? \App\Support\CompanyContext::company();

        if (! $company || ! \App\Support\Settings::isCompanyKey($key)) {
            throw new InvalidArgumentException("{$key} is not a company setting, or there is no company.");
        }

        $values = [];
        data_set($values, $key, $value);
        \App\Support\Settings::putForCompany($company, $values);
    }
}

if (!function_exists('set_user_preference')) {
    /**
     * Set a user preference value.
     *
     * @param string $key Dot notation key (e.g., 'appearance.theme')
     * @param mixed $value Value to set
     * @param User|null $user User instance (defaults to authenticated user)
     * @return bool
     */
    function set_user_preference(string $key, mixed $value, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        $user->setPreference($key, $value)->save();
        return true;
    }
}

if (!function_exists('all_user_preferences')) {
    /**
     * Get all user preferences merged with defaults.
     *
     * @param User|null $user User instance (defaults to authenticated user)
     * @return array
     */
    function all_user_preferences(?User $user = null): array
    {
        return \App\Support\Settings::resolve($user);
    }
}

if (!function_exists('recordsPerPage')) {
    function recordsPerPage()
    {
        $user = auth()->user();
        if ($user) {
            return $user->preferences['appearance']['records_per_page'] ?? 15;
        }
        return 15;
    }
}

if (!function_exists('balance_nature_format')) {
    /**
     * Get the balance nature format for display (e.g., 'with_nature' or 'without_nature').
     *
     * @return string
     */
    function balanceNatureFormat(): string
    {
        // A company setting since phase 6.
        return (string) \App\Support\Settings::get('appearance.balance_nature_format', 'with_nature');
    }
}


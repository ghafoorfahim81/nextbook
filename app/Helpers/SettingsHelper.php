<?php

use App\Models\User;

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
        $user = $user ?? auth()->user();
        
        if (!$user) {
            return data_get(User::DEFAULT_PREFERENCES, $key, $default);
        }

        return $user->getPreference($key, $default);
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
        $user = $user ?? auth()->user();
        
        if (!$user) {
            return User::DEFAULT_PREFERENCES;
        }

        return $user->getAllPreferences();
    }
}


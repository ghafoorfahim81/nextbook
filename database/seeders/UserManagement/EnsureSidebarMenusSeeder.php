<?php

namespace Database\Seeders\UserManagement;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class EnsureSidebarMenusSeeder extends Seeder
{
    public function run(): void
    {
        $defaultMenus = data_get(
            User::DEFAULT_PREFERENCES,
            'appearance.sidebar_menus',
            []
        );

        if (! is_array($defaultMenus) || $defaultMenus === []) {
            return;
        }

        User::query()
            ->select(['id', 'preferences'])
            ->chunkById(100, function ($users) use ($defaultMenus): void {
                foreach ($users as $user) {
                    $preferences = $user->preferences ?? User::DEFAULT_PREFERENCES;
                    $menus = data_get($preferences, 'appearance.sidebar_menus');

                    // Only seed the key for users who have never set it. Merging
                    // the defaults back in would silently re-enable every menu a
                    // user had deliberately hidden.
                    if (is_array($menus)) {
                        continue;
                    }

                    data_set($preferences, 'appearance.sidebar_menus', $defaultMenus);

                    $user->forceFill([
                        'preferences' => $preferences,
                    ])->saveQuietly();

                    Cache::forget('inertia:user:' . $user->id . ':preferences');
                }
            });
    }
}

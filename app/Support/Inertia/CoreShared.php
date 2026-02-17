<?php

namespace App\Support\Inertia;

use App\Models\Administration\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class CoreShared
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Request $request): array
    {
        $cacheDuration = 60 * 60;
        $locale = app()->getLocale();
        $direction = in_array($locale, ['fa', 'ps'], true) ? 'rtl' : 'ltr';
        $user = $request->user();

        $activeBranchId = CacheKey::branchId($request);
        $activeBranchName = null;

        if ($activeBranchId) {
            $activeBranchName = Cache::remember(
                CacheKey::forCompanyBranchLocale($request, "branch_name:{$activeBranchId}"),
                $cacheDuration,
                fn() => Branch::query()->find($activeBranchId)?->name
            );
        }

        $permissions = $user
            ? Cache::remember(
                CacheKey::forUser($request, 'permissions'),
                $cacheDuration,
                fn() => $user->getAllPermissions()->pluck('name')->toArray()
            )
            : [];

        $roles = $user
            ? Cache::remember(
                CacheKey::forUser($request, 'roles'),
                $cacheDuration,
                fn() => $user->getRoleNames()->toArray()
            )
            : [];

        $roleSlugs = $user
            ? Cache::remember(
                CacheKey::forUser($request, 'role_slugs'),
                $cacheDuration,
                fn() => $user->roles->pluck('slug')->toArray()
            )
            : [];

        $userPreferences = $user
            ? Cache::remember(
                CacheKey::forUser($request, 'preferences'),
                $cacheDuration,
                fn() => $user->preferences
            )
            : null;
        // $recordsPerPage = $userPreferences['appearance']['records_per_page'] ?? 10;
        // Cache::put('recordsPerPage', $recordsPerPage, $cacheDuration);
        return [
            'locale' => $locale,
            'direction' => $direction,
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'branch_id' => $user->branch_id,
                    'branch_name' => $activeBranchName,
                    'permissions' => $permissions,
                    'roles' => $roles,
                    'role_slugs' => $roleSlugs, 
                    'calendar_type' => $user->company?->calendar_type,
                ] : null,
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
            ],
            'user_preferences' => $userPreferences,
            'activeBranchId' => $activeBranchId,
            'activeBranchName' => $activeBranchName, 
        ];
    }
}

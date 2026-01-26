<?php

namespace App\Support\Inertia;

use Illuminate\Http\Request;

final class CacheKey
{
    public static function forCompanyBranchLocale(Request $request, string $name): string
    {
        $companyId = self::companyId($request) ?? 'none';
        $branchId = self::branchId($request) ?? 'none';
        $locale = app()->getLocale();

        return "inertia:company:{$companyId}:branch:{$branchId}:locale:{$locale}:{$name}";
    }

    public static function forUser(Request $request, string $name): string
    {
        $userId = $request->user()?->id ?? 'guest';

        return "inertia:user:{$userId}:{$name}";
    }

    public static function companyId(Request $request): ?int
    {
        $user = $request->user();

        if ($user?->company_id) {
            return $user->company_id;
        }

        return $user?->company?->id;
    }

    public static function branchId(Request $request): ?int
    {
        if (app()->bound('active_branch_id')) {
            return app('active_branch_id');
        }

        return $request->user()?->branch_id;
    }
}

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

    /**
     * Retrieve the company ID associated with the current request's user.
     *
     * Returns the user's direct company_id if available, otherwise uses the company relation.
     */
    public static function companyId(Request $request): ?string
    {
        $user = $request->user();

        if ($user?->company_id) {
            return (string) $user->company_id;
        }

        return $user?->company?->id ? (string) $user->company->id : null;
    }

    public static function branchId(Request $request): ?string
    {
        if (app()->bound('active_branch_id')) {
            $branchId = app('active_branch_id');
            return $branchId ? (string) $branchId : null;
        }

        $branchId = $request->user()?->branch_id;

        return $branchId ? (string) $branchId : null;
    }
}

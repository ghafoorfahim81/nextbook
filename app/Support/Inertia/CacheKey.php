<?php

namespace App\Support\Inertia;

use Illuminate\Http\Request;

final class CacheKey
{
    public static function forCompanyBranchLocale(Request $request, string $name): string
    {
        return self::build(
            self::companyId($request) ?? 'none',
            self::branchId($request) ?? 'none',
            app()->getLocale(),
            $name,
        );
    }

    /**
     * Build a lookup key from explicit parts.
     *
     * Used when clearing a key for a branch or locale other than the current one.
     */
    public static function build(string $companyId, string $branchId, string $locale, string $name): string
    {
        return "inertia:company:{$companyId}:branch:{$branchId}:locale:{$locale}:{$name}";
    }

    /**
     * A value that derives from the company alone — the business profile — so
     * every user acting in it shares one entry, and a user who switches company
     * reads the other company's.
     */
    public static function forCompany(?string $companyId, string $name): string
    {
        return 'inertia:company:'.($companyId ?? 'none').":{$name}";
    }

    public static function forUser(Request $request, string $name): string
    {
        $userId = $request->user()?->id ?? 'guest';

        return "inertia:user:{$userId}:{$name}";
    }

    /**
     * The company the request acts in, which since phase 5 need not be the
     * user's home company. Keys built from the home company would hand one
     * company's cached lookups to a user who has switched to another.
     */
    public static function companyId(Request $request): ?string
    {
        if (app()->bound('active_company_id')) {
            $companyId = app('active_company_id');

            return $companyId ? (string) $companyId : null;
        }

        $companyId = $request->user()?->company_id;

        return $companyId ? (string) $companyId : null;
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

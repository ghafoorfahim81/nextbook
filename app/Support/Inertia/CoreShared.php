<?php

namespace App\Support\Inertia;

use App\Models\Administration\Branch;
use App\Services\NotificationService;
use App\Services\UserAccessService;
use App\Support\BusinessProfile;
use App\Support\CompanyContext;
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
                CacheKey::forUser($request, 'permissions:'.CacheKey::companyId($request)),
                $cacheDuration,
                fn() => $user->getAllPermissions()->pluck('name')->toArray()
            )
            : [];

        $roles = $user
            ? Cache::remember(
                CacheKey::forUser($request, 'roles:'.CacheKey::companyId($request)),
                $cacheDuration,
                fn() => $user->getRoleNames()->toArray()
            )
            : [];

        $roleSlugs = $user
            ? Cache::remember(
                CacheKey::forUser($request, 'role_slugs:'.CacheKey::companyId($request)),
                $cacheDuration,
                fn() => $user->roles->pluck('slug')->toArray()
            )
            : [];

        $userPreferences = $user
            ? \App\Support\Settings::overlayCompany(
                Cache::remember(
                    CacheKey::forUser($request, 'preferences'),
                    $cacheDuration,
                    // Merge with defaults so newly introduced preference keys are
                    // always present for the frontend, even for users whose stored
                    // preferences predate them.
                    fn() => $user->getAllPreferences()
                ),
                // Company settings go over the cached personal ones on every
                // request, so a change reaches the whole company at once.
                CompanyContext::company()
            )
            : null;
        // $recordsPerPage = $userPreferences['appearance']['records_per_page'] ?? 10;
        // Cache::put('recordsPerPage', $recordsPerPage, $cacheDuration);
        return [
            'locale' => $locale,
            'direction' => $direction,
            // Shown in the guest footer so users can quote their build.
            'app' => [
                'name' => config('app.name'),
                'version' => config('app.version'),
                // How long a soft-deleted record stays recoverable. The delete
                // confirmation quotes it, and it must not drift from the window
                // the cleanup job actually enforces.
                'deleted_records_retention_days' => \App\Services\DeletedRecordService::RETENTION_DAYS,
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'profile_photo_path' => $user->profile_photo_path,
                    'profile_photo_url' => $user->profile_photo_url,
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                    // The branch the request acts in, not the home branch.
                    'branch_id' => $activeBranchId,
                    'branch_name' => $activeBranchName,
                    'permissions' => $permissions,
                    'roles' => $roles,
                    'role_slugs' => $roleSlugs,
                    'calendar_type' => CompanyContext::company()?->calendar_type,
                    // Drives the one-time "check the user manual" dialog. True
                    // only for a user who belongs to a company and has never
                    // opened the manual or dismissed the prompt.
                    'show_manual_prompt' => (bool) $user->company_id
                        && blank(data_get($userPreferences, 'onboarding.manual_prompt_dismissed_at')),
                ] : null,
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'print_url' => fn() => $request->session()->get('print_url'),
                // Shown by the layout on whatever page the user lands on —
                // unlike `error`, which only some pages display. Used when the
                // request was refused for a reason outside the page itself (a
                // company switched in another tab).
                'context_notice' => fn() => $request->session()->get('context_notice'),
            ],
            'user_preferences' => $userPreferences,
            // Which item fields, sections and tracking defaults this trade
            // uses. Cached per company: it derives from the company alone, and
            // since phase 5 a user's companies can be different trades.
            'business_profile' => $user
                ? Cache::remember(
                    CacheKey::forCompany(CacheKey::companyId($request), 'business_profile'),
                    $cacheDuration,
                    fn() => BusinessProfile::for(CompanyContext::company())->toArray()
                )
                : null,
            'notification_center' => $user
                ? app(NotificationService::class)->getNotificationCenter($user)
                : ['unread_count' => 0, 'items' => []],
            // The company this page is rendered in. app.js sends it back with
            // every request from the page; RejectStaleCompanyContext refuses a
            // write whose page company is not the session's any more.
            'activeCompanyId' => CacheKey::companyId($request),
            // The top-bar switchers (phase 5). Lazy, so a partial reload that
            // does not ask for it skips the queries.
            'switcher' => fn () => $user
                ? app(UserAccessService::class)->switcherOptions($user, CacheKey::companyId($request))
                : ['companies' => [], 'branches' => []],
            'activeBranchId' => $activeBranchId,
            'activeBranchName' => $activeBranchName,
            'balanceNatureFormat' => balanceNatureFormat(),

        ];
    }
}

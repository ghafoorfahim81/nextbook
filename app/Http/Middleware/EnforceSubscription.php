<?php

namespace App\Http\Middleware;

use App\Exceptions\SubscriptionException;
use App\Services\SubscriptionService;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a company whose subscription has expired, or was blocked, read-only
 * (phase 10): its people still sign in, look at everything, print and export,
 * but cannot save, post or delete until it is renewed. Nothing is locked or
 * removed — the blocking has to be soft, because a company that cannot read
 * its own books cannot pay to get them back.
 *
 * Only writes are refused. The few that are not about the company's books
 * — signing out, language, switching company, notifications, one's own
 * profile and personal settings — still go through, and so does the platform
 * admin, who runs the platform and may need to act in any company.
 */
class EnforceSubscription
{
    /** Write requests that stay open to a read-only company. */
    private const EXEMPT = [
        'logout', 'locale', 'switch-company', 'switch-branch',
        'notifications', 'notifications/*', 'api/notifications/*',
        'onboarding/*', 'preferences', 'preferences/*',
        'user/*', 'two-factor-*', 'platform/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->is(...self::EXEMPT)) {
            return $next($request);
        }

        $company = CompanyContext::company();
        $subscription = $company ? app(SubscriptionService::class)->forCompany($company) : null;

        if ($subscription === null
            || $subscription->allowsWrites()
            || $request->user()?->isPlatformAdmin()) {
            return $next($request);
        }

        throw new SubscriptionException(
            $subscription->isBlocked()
                ? __('messages.subscription.blocked', ['reason' => $subscription->block_reason ?: '—'])
                : __('messages.subscription.expired', ['date' => $subscription->ends_on->toDateString()])
        );
    }
}

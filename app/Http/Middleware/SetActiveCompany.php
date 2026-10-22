<?php

namespace App\Http\Middleware;

use App\Services\UserAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the company the current request acts in and bind it for the rest of
 * the request.
 *
 * The company is derived from the authenticated user's own membership and is
 * never read from a request parameter. A `company_id` posted by the browser
 * must not be able to widen what the request can see — that is the hole the
 * architecture plan calls out, and the reason this runs before everything that
 * reads data.
 *
 * Two values are bound:
 *
 *   active_company_id      the single company documents are written in
 *   accessible_company_ids every company the user may read
 *
 * They are separate because reporting may legitimately span companies while
 * posting never does. `accessible_company_ids` is an array today holding one
 * id; it is shaped that way deliberately so the scopes built on top of it are
 * already `whereIn(...)` by the time a user can belong to several companies.
 *
 * This must run before SetActiveBranch, which validates its branch against the
 * company resolved here.
 */
class SetActiveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->company_id) {
            $companyId = $this->resolve($request, $user);

            app()->instance('active_company_id', $companyId);
            // Still the one active company, not every company the user may
            // reach: reading across companies belongs to group reporting
            // (phase 9), and the row level security policies admit one company
            // per connection regardless.
            app()->instance('accessible_company_ids', [$companyId]);
            app()->forgetInstance('active_company');
        }

        return $next($request);
    }

    /**
     * The company picked with the switcher (phase 5), kept in the session, as
     * long as the user may still act in it; otherwise their home company.
     *
     * The session value is checked on every request rather than trusted: access
     * can be withdrawn while a session is alive, and a session is only as
     * trustworthy as whoever last wrote to it.
     */
    private function resolve(Request $request, $user): string
    {
        $home = (string) $user->company_id;

        if (! $request->hasSession()) {
            return $home;
        }

        $picked = $request->session()->get('active_company_id');

        if (! $picked || $picked === $home) {
            return $home;
        }

        if (app(UserAccessService::class)->canAccessCompany($user, (string) $picked)) {
            return (string) $picked;
        }

        $request->session()->forget(['active_company_id', 'active_branch_id']);

        return $home;
    }
}

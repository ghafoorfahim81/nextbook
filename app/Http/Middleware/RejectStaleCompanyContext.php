<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse a write sent from a page that was opened in a different company
 * (phase 5).
 *
 * The active company lives in the session, which every tab shares. Switch
 * company in one tab and a form left open in another still shows the old
 * company — and would save into the new one. The plan's rule is that the
 * company must not change under a request, so each page tells the server which
 * company it was rendered in (the X-Nextbook-Company header, set in app.js from
 * the page's props) and a write whose page disagrees with the session is turned
 * away rather than filed in the other company's books.
 *
 * Only writes are checked: a stale GET just renders in the current company.
 * A request without the header — the test suite, API clients — is let through;
 * the guard protects a person with two tabs, not the company boundary itself,
 * which the policies and the access checks already hold.
 */
class RejectStaleCompanyContext
{
    public const HEADER = 'X-Nextbook-Company';

    /**
     * Routes whose whole purpose is to leave the page's company: switching
     * company from a page of the old one, and signing out of it.
     */
    private const EXEMPT_ROUTES = ['companies.switch', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! app()->bound('active_company_id')) {
            return $next($request);
        }

        $pageCompany = $request->header(self::HEADER);

        if (! $pageCompany || $pageCompany === (string) app('active_company_id')) {
            return $next($request);
        }

        if ($request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        $message = __('general.company_changed_in_another_tab');

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 409);
        }

        // Home rather than back: the page the form came from belongs to the
        // other company, and its record may not exist in this one.
        // context_notice, not error: the layout shows it on any page, while
        // `error` is only displayed by pages that look for it — the home page
        // does not, and the refusal went unseen.
        return redirect()->route('home')->with('context_notice', $message);
    }
}

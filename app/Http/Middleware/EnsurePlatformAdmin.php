<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the /platform panel to whoever runs the platform (phase 10).
 *
 * A customer's super-admin is not one: they run their own company, and the
 * panel lists every company. Anyone else gets a 404 rather than a 403, so the
 * panel is not confirmed to exist. The panel as a whole is off unless
 * config('platform.panel_enabled') is set.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('platform.panel_enabled') && $request->user()?->isPlatformAdmin(), 404);

        return $next($request);
    }
}

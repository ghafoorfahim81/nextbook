<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use App\Support\Modules;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a URL that belongs to a module the company has switched off
 * (config/modules.php), so a module is not reachable by typing its address
 * once the sidebar no longer shows it. Its data is left alone.
 *
 * After SetActiveCompany: whether a module is on is the active company's
 * setting. No company (sign-in, sign-up) means nothing to check.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $module = Modules::forPath($request->path());

        if ($module !== null && CompanyContext::company() && ! Modules::isEnabled($module)) {
            // Rendered by the AuthorizationException handler: the Forbidden page,
            // or a JSON 403 for a request that wants JSON.
            throw new AuthorizationException(__('messages.errors.module_disabled', [
                'module' => __("general.modules.{$module}"),
            ]));
        }

        return $next($request);
    }
}

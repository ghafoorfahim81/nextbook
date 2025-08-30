<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckCompany
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Skip for unauthenticated users or if already on company.create/company.store route
        if (!$user || $request->routeIs('company.*') || $request->routeIs('logout') || $request->routeIs('profile.*')) {
            return $next($request);
        }

        // Check if user has a company
        if (!$user->company_id) {
            // Check if user is already on the company creation page to prevent redirect loops
            if ($request->routeIs('company.create') || $request->routeIs('company.store')) {
                return $next($request);
            }
            return redirect()->route('company.create');
        }

        // Verify the company exists
        $company = $user->company;
        if (!$company) {
            // If company doesn't exist, clear the company_id and redirect to create company
            $user->company_id = null;
            $user->save();
            return redirect()->route('company.create');
        }

        // Set company in the request for easy access in controllers
        $request->attributes->add(['company' => $company]);

        return $next($request);
    }
}

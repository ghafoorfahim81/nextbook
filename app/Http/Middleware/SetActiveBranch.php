<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetActiveBranch
{
    /**
     * Handle an incoming request.
     *
     * Resolve the active branch for the current request and store it
     * in the service container as `active_branch_id`.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Only resolve branch for authenticated users
        if ($user) {
            $branchId = null;

            // Super-admin can switch branches via session, but falls back to own branch
            if (method_exists($user, 'hasRole') && $user->roles->contains('slug', 'super-admin')) {
                $branchId = $request->session()->get('active_branch_id', $user->branch_id);
            } else {
                // Normal users are always locked to their own branch
                $branchId = $user->branch_id;
            }

            if ($branchId) {
                app()->instance('active_branch_id', $branchId);
            }
        }

        return $next($request);
    }
}



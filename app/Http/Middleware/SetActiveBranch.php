<?php

namespace App\Http\Middleware;

use App\Models\Administration\Branch;
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

            // A branch id reaching here from the session is only as trustworthy
            // as the session, and a company boundary is not something a stale
            // or tampered session value may cross. Checked against the company
            // SetActiveCompany resolved from the user's membership; an id that
            // fails falls back to the user's own branch rather than being
            // honoured.
            if ($branchId && ! $this->belongsToActiveCompany($branchId)) {
                $branchId = $user->branch_id;
            }

            if ($branchId) {
                app()->instance('active_branch_id', $branchId);
            }
        }

        return $next($request);
    }

    /**
     * Whether the branch sits under the company resolved for this request.
     *
     * Unscoped on purpose: the point of the check is to compare the branch's
     * own company against the active one, which a company-scoped query would
     * have already decided.
     */
    private function belongsToActiveCompany(string $branchId): bool
    {
        if (! app()->bound('active_company_id')) {
            return true;
        }

        return Branch::withoutGlobalScopes()
            ->whereKey($branchId)
            ->where('company_id', app('active_company_id'))
            ->exists();
    }
}



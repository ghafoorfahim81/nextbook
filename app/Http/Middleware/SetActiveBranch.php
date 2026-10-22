<?php

namespace App\Http\Middleware;

use App\Services\UserAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetActiveBranch
{
    /**
     * Resolve the branch the current request acts in and bind it as
     * `active_branch_id`.
     *
     * The branch picked with the switcher is kept in the session. It is honoured
     * only when it is one of the branches UserAccessService allows this user in
     * the company SetActiveCompany resolved — so a stale or tampered session
     * value can neither cross into another company nor reach a branch the user
     * was never given. Otherwise the request lands in the user's default branch
     * for that company: their home branch when it is there, else the main one.
     *
     * Runs after SetActiveCompany and SetDatabaseCompanyContext.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && app()->bound('active_company_id')) {
            $access = app(UserAccessService::class);
            $companyId = (string) app('active_company_id');

            $picked = $request->hasSession() ? $request->session()->get('active_branch_id') : null;

            $branchId = $picked && $access->canAccessBranch($user, $companyId, (string) $picked)
                ? (string) $picked
                : $access->defaultBranchId($user, $companyId);

            if ($picked && $branchId !== (string) $picked) {
                $request->session()->forget('active_branch_id');
            }

            if ($branchId) {
                app()->instance('active_branch_id', $branchId);
            }
        }

        return $next($request);
    }
}

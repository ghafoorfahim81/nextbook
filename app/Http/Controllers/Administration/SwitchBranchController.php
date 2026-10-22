<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Services\UserAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SwitchBranchController extends Controller
{
    /**
     * Switch the branch the user acts in, within the active company.
     *
     * Open to anyone with access to more than one branch (phase 5), not only
     * super-admins. Which branches that is comes from UserAccessService, scoped
     * to the active company: a bare `exists:branches,id` would accept any branch
     * in the database, and once switched every branch-scoped query follows.
     *
     * The choice lives in the session. It no longer overwrites users.branch_id —
     * that is the home branch, and a switch made in one session must not move
     * where every other session of the same user lands.
     */
    public function __invoke(Request $request, UserAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'string'],
        ]);

        $branchId = $validated['branch_id'];
        $companyId = app()->bound('active_company_id') ? (string) app('active_company_id') : null;

        if (! $access->canAccessBranch($request->user(), $companyId, $branchId)) {
            throw ValidationException::withMessages([
                'branch_id' => __('validation.exists', ['attribute' => 'branch']),
            ]);
        }

        $request->session()->put('active_branch_id', $branchId);

        return back();
    }
}

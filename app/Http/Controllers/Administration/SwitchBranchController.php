<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchBranchController extends Controller
{
    /**
     * Switch the active branch for the current super-admin user.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Only super-admins are allowed to switch branches.
        if (!$user || !method_exists($user, 'hasRole') || !$user->hasRole('super-admin')) {
            abort(403);
        }

        $validated = $request->validate([
            'branch_id' => ['required', 'string', 'exists:branches,id'],
        ]);
        $branchId = $validated['branch_id'];

        cache()->forget('auth.user');
        // Store the active branch in the session so subsequent requests
        // // (and page refreshes) resolve to this branch.
        // $request->session()->put('active_branch_id', $branchId);

        // // Clear any cached branch-name for the previous branch so it will be
        // // recomputed on the next request.
        // cache()->forget('branch_name_' . ($user->branch_id ?? $branchId));

        // Also update the user's default branch so new sessions/logins
        // start on the last selected branch.
        if ($user->branch_id !== $branchId) {
            $user->branch_id = $branchId;
            $user->save();
        }
 

        return back();
    }
}



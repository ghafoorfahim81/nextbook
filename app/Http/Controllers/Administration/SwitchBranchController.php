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
        cache()->forget('active_branch_id');
        cache()->forget('active_branch_name');
        $request->session()->put('active_branch_id', $validated['branch_id']);

        return back();
    }
}



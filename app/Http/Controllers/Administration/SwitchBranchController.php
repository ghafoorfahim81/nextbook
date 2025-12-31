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
        cache()->forget('stores');
        cache()->forget('branches');
        cache()->forget('categories');
        cache()->forget('accounts');
        cache()->forget('accountTypes');
        cache()->forget('unitMeasures');
        cache()->forget('sizes');
        cache()->forget('colors');
        cache()->forget('brands');
        cache()->forget('items');
        cache()->forget('stocks');
        cache()->forget('currencies');
        cache()->forget('main_branch');
        cache()->forget('home_currency');
        cache()->forget('account_types');
        cache()->forget('roles');
        cache()->forget('user_preferences');
        cache()->forget('transaction_types');
        cache()->forget('transaction_statuses');
        cache()->forget('capital_accounts');
        cache()->forget('drawing_accounts');
        cache()->forget('ledgers');
        cache()->forget('gl_accounts');
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



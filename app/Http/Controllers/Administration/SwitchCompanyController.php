<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Services\UserAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SwitchCompanyController extends Controller
{
    /**
     * Switch the company the user acts in (phase 5).
     *
     * Only to a company UserAccessService gives this user — the posted id is
     * checked, never trusted. The choice lives in the session, and the branch
     * picked in the previous company is dropped with it, so the request after
     * the switch lands in the user's default branch of the new company.
     *
     * Goes to the home page rather than back: the page the user was on belongs
     * to the company they just left, and its record may not exist in this one.
     */
    public function __invoke(Request $request, UserAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $access->canAccessCompany($user, $validated['company_id'])) {
            throw ValidationException::withMessages([
                'company_id' => __('validation.exists', ['attribute' => 'company']),
            ]);
        }

        if (! $access->defaultBranchId($user, $validated['company_id'])) {
            // A company with no branch this user may act in would leave every
            // branch-scoped page empty and every posting without a branch.
            throw ValidationException::withMessages([
                'company_id' => __('general.no_branch_access_in_company'),
            ]);
        }

        $request->session()->put('active_company_id', $validated['company_id']);
        $request->session()->forget('active_branch_id');

        return redirect()->route('home');
    }
}

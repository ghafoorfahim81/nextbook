<?php

namespace App\Http\Controllers\UserManagement;

use App\Enums\BusinessType;
use App\Http\Controllers\Controller;
use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Administration\UserBranchAccess;
use App\Models\Administration\UserCompanyAccess;
use App\Models\User;
use App\Services\UserAccessService;
use App\Support\CompanyContext;
use Illuminate\Http\Request;

/**
 * Which companies, and which of their branches, a user may act in (phase 5).
 *
 * Super-admins only, by decision. The screen shows the companies the
 * super-admin belongs to and nothing else, and saving changes only grants
 * inside those (UserAccessService::syncAccess). The user being edited has to
 * belong to the company the request acts in, as on every user screen.
 */
class UserAccessController extends Controller
{
    public function __construct(private readonly UserAccessService $access)
    {
        $this->middleware(function (Request $request, \Closure $next) {
            abort_unless($request->user()->isSuperAdmin(), 403);

            $target = $request->route('user');
            abort_unless(
                $target instanceof User
                    && $this->access->membersOf((string) CompanyContext::companyId())->whereKey($target->getKey())->exists(),
                404
            );

            return $next($request);
        });
    }

    public function edit(Request $request, User $user)
    {
        $companyIds = $this->access->accessibleCompanyIds($request->user());

        $grantedCompanies = UserCompanyAccess::query()->where('user_id', $user->id)->pluck('company_id')->map(fn ($id) => (string) $id)->all();
        $grantedBranches = UserBranchAccess::query()->where('user_id', $user->id)->pluck('branch_id')->map(fn ($id) => (string) $id)->all();

        $branches = Branch::query()
            ->withoutGlobalScope('companySpecific')
            ->whereIn('company_id', $companyIds)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get(['id', 'company_id', 'name', 'is_main'])
            ->groupBy('company_id');

        $companies = Company::query()->whereKey($companyIds)->get()
            ->sortBy(fn (Company $company) => array_search((string) $company->id, $companyIds, true))
            ->map(fn (Company $company) => [
                'id' => (string) $company->id,
                'name' => $company->localizedName(),
                'business_type' => ($company->business_type instanceof BusinessType
                    ? $company->business_type
                    : BusinessType::tryFrom((string) $company->business_type))?->getLabel(),
                'is_home' => (string) $company->id === (string) $user->company_id,
                'branches' => ($branches[$company->id] ?? collect())->map(fn (Branch $branch) => [
                    'id' => (string) $branch->id,
                    'name' => (string) $branch->name,
                    'is_main' => (bool) $branch->is_main,
                    'is_home' => (string) $branch->id === (string) $user->branch_id,
                ])->values(),
            ])
            ->values();

        return inertia('UserManagement/Users/Access', [
            'user' => ['id' => (string) $user->id, 'name' => $user->name, 'email' => $user->email],
            'userIsSuperAdmin' => $user->isSuperAdmin(),
            'companies' => $companies,
            'granted' => [
                'companies' => array_values(array_unique(array_filter([...$grantedCompanies, (string) $user->company_id]))),
                'branches' => array_values(array_unique(array_filter([...$grantedBranches, (string) $user->branch_id]))),
            ],
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'companies' => ['array'],
            'companies.*' => ['string'],
            'branches' => ['array'],
            'branches.*' => ['string'],
        ]);

        $this->access->syncAccess($user, $validated['companies'] ?? [], $validated['branches'] ?? [], $request->user());

        return redirect()->route('users.access.edit', $user)
            ->with('success', __('general.updated_successfully', ['resource' => __('general.resource.user')]));
    }
}

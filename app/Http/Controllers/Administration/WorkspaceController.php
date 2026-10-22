<?php

namespace App\Http\Controllers\Administration;

use App\Enums\BusinessType;
use App\Http\Controllers\Controller;
use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Services\UserAccessService;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The company and branch pickers (phase 5), reached from the user menu.
 *
 * The companies page is also where a super-admin starts a new company: it
 * replaced the old companies list, whose columns (legal name, industry, …)
 * no longer matched the table. Both pages list only what UserAccessService
 * gives the user; switching posts to SwitchCompanyController /
 * SwitchBranchController, which check again.
 */
class WorkspaceController extends Controller
{
    public function __construct(private readonly UserAccessService $access)
    {
    }

    public function companies(Request $request): Response
    {
        $user = $request->user();
        $ids = $this->access->accessibleCompanyIds($user);
        $activeId = app()->bound('active_company_id') ? (string) app('active_company_id') : null;

        $branchCounts = Branch::query()
            ->withoutGlobalScope('companySpecific')
            ->whereIn('company_id', $ids)
            ->selectRaw('company_id, count(*) as total')
            ->groupBy('company_id')
            ->pluck('total', 'company_id');

        $companies = Company::query()->whereKey($ids)->get()
            ->sortBy(fn (Company $company) => array_search((string) $company->id, $ids, true))
            ->map(fn (Company $company) => [
                'id' => (string) $company->id,
                'name' => $company->localizedName(),
                // Cast to the enum on the model; tolerate a raw value too.
                'business_type' => ($company->business_type instanceof BusinessType
                    ? $company->business_type
                    : BusinessType::tryFrom((string) $company->business_type))?->getLabel(),
                'city' => $company->city,
                'logo_url' => $company->logo_url,
                'branches' => (int) ($branchCounts[$company->id] ?? 0),
                'is_active' => (string) $company->id === $activeId,
                'is_home' => (string) $company->id === (string) $user->company_id,
                // A super-admin acts in every branch; anyone else needs one.
                'can_switch' => $this->access->defaultBranchId($user, (string) $company->id) !== null,
            ])
            ->values();

        return inertia('Workspace/Companies', [
            'companies' => $companies,
            'canCreate' => $user->isSuperAdmin(),
        ]);
    }

    public function branches(Request $request): Response
    {
        $user = $request->user();
        $companyId = app()->bound('active_company_id') ? (string) app('active_company_id') : null;
        $ids = $this->access->allowedBranchIds($user, $companyId);
        $activeId = app()->bound('active_branch_id') ? (string) app('active_branch_id') : null;

        $branches = Branch::query()
            ->withoutGlobalScope('companySpecific')
            ->whereKey($ids)
            ->get()
            ->sortBy(fn (Branch $branch) => array_search((string) $branch->id, $ids, true))
            ->map(fn (Branch $branch) => [
                'id' => (string) $branch->id,
                'name' => (string) $branch->name,
                'location' => $branch->location,
                'is_main' => (bool) $branch->is_main,
                'is_active' => (string) $branch->id === $activeId,
                'is_home' => (string) $branch->id === (string) $user->branch_id,
            ])
            ->values();

        return inertia('Workspace/Branches', [
            'branches' => $branches,
            'companyName' => \App\Support\CompanyContext::company()?->localizedName(),
        ]);
    }
}

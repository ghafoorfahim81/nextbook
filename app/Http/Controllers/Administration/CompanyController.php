<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\CompanyStoreRequest;
use App\Http\Requests\Administration\CompanyUpdateRequest;
use App\Http\Resources\Administration\CompanyResource;
use App\Models\Administration\Company;
use App\Services\CompanySetupService;
use App\Services\UserAccessService;
use Illuminate\Http\Request;

/**
 * The companies screen: only ever the companies the signed-in user belongs to.
 *
 * `companies` carries no company_id, so neither the row level security
 * policies nor a company scope bound it — and the policies cannot either,
 * because the super-admin bypass in AuthServiceProvider answers before they
 * run, and super-admins are exactly who use this screen. Without the checks
 * below it listed every company in the database and let any super-admin read,
 * change, delete or restore another customer's company by its id.
 */
class CompanyController extends Controller
{
    public function __construct(private readonly UserAccessService $access)
    {
        $this->authorizeResource(Company::class, 'company');

        // As middleware rather than in each action: it has to run before the
        // form request validates, or another company's id gets a validation
        // answer instead of a 404.
        $this->middleware(function (Request $request, \Closure $next) {
            $company = $request->route('company');

            if ($company instanceof Company) {
                $this->ensureMember($request, $company);
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $companies = Company::with(['createdBy', 'updatedBy'])
            ->whereKey($this->access->accessibleCompanyIds($request->user()))
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Administration/Companies/Index', [
            'companies' => CompanyResource::collection($companies),
        ]);
    }

    /**
     * Adding a company beside the ones the user has (phase 5): the same full
     * form as sign-up, posting here instead.
     */
    public function create(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return inertia('Company/Create', ['submitRoute' => 'companies.store']);
    }

    /**
     * Super-admins only, by decision: a company is a set of books, and who may
     * open one is not a permission to hand out with a role. The new company is
     * provisioned and granted to its creator; their home stays where it was.
     */
    public function store(CompanyStoreRequest $request, CompanySetupService $setup)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $result = $setup->create($request->companyAttributes(), $request->validated('base_currency'), $request->user());

        if ($request->wantsJson()) {
            return response()->json(['redirect' => route('companies.index'), 'summary' => $result['summary']], 201);
        }

        return redirect()->route('companies.index')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.company')]));
    }

    public function show(Request $request, Company $company)
    {
        return response()->json($company);
    }

    public function update(CompanyUpdateRequest $request, Company $company)
    {
        $company->update($request->validated());

        return response()->json($company);
    }

    public function destroy(Request $request, Company $company)
    {
        $company->delete();

        return response()->noContent();
    }

    public function restore(Request $request, Company $company)
    {
        $company->restore();
        return redirect()->route('companies.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.company')]));
    }

    /**
     * 404, not 403: another customer's company should not be confirmed to
     * exist by the answer.
     */
    private function ensureMember(Request $request, Company $company): void
    {
        abort_unless($this->access->isMemberOf($request->user(), (string) $company->getKey()), 404);
    }
}

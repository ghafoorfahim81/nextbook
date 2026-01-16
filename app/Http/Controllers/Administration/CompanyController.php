<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\CompanyStoreRequest;
use App\Http\Requests\Administration\CompanyUpdateRequest;
use App\Http\Resources\Administration\CompanyCollection;
use App\Http\Resources\Administration\CompanyResource;
use App\Models\Administration\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompanyController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Company::class, 'company');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $companies = Company::with('parent')
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Administration/Companies/Index', [
            'companies' => CompanyResource::collection($companies),
        ]);
    }

    public function store(CompanyStoreRequest $request)
    {
        $company = Company::create($request->validated());

        return response()->json($company);
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
}

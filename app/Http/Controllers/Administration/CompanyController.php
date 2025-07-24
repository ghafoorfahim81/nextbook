<?php
namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\CompanyStoreRequest;
use App\Http\Requests\Administration\CompanyUpdateRequest;
use App\Http\Resources\Administration\CompanyResource;
use App\Models\Administration\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $companies = Company::search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Administration/Companies/Index', [
            'companies' => CompanyResource::collection($companies),
        ]);
    }

    public function store(CompanyStoreRequest $request)
    {
        Company::create($request->validated());
        return redirect()->route('companies.index')->with('success', 'Company created successfully.');
    }

    public function show(Request $request, Company $company)
    {
        return new CompanyResource($company);
    }

    public function update(CompanyUpdateRequest $request, Company $company)
    {
        $company->update($request->validated());
        return redirect()->back();
    }

    public function destroy(Request $request, Company $company)
    {
        $company->delete();
        return back();
    }
}

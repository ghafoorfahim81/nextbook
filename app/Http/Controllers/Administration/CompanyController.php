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
    public function index(Request $request): Response
    {
        $companies = Company::all();

        return new CompanyCollection($companies);
    }

    public function store(CompanyStoreRequest $request): Response
    {
        $company = Company::create($request->validated());

        return new CompanyResource($company);
    }

    public function show(Request $request, Company $company): Response
    {
        return new CompanyResource($company);
    }

    public function update(CompanyUpdateRequest $request, Company $company): Response
    {
        $company->update($request->validated());

        return new CompanyResource($company);
    }

    public function destroy(Request $request, Company $company): Response
    {
        $company->delete();

        return response()->noContent();
    }
}

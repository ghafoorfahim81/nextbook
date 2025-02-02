<?php

namespace App\Http\Controllers\Administration;

use App\Administration\Designation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\DesignationStoreRequest;
use App\Http\Requests\Administration\DesignationUpdateRequest;
use App\Http\Resources\Administration\DesignationCollection;
use App\Http\Resources\Administration\DesignationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DesignationController extends Controller
{
    public function index(Request $request): Response
    {
        $designations = Designation::all();

        return new DesignationCollection($designations);
    }

    public function store(DesignationStoreRequest $request): Response
    {
        $designation = Designation::create($request->validated());

        return new DesignationResource($designation);
    }

    public function show(Request $request, Designation $designation): Response
    {
        return new DesignationResource($designation);
    }

    public function update(DesignationUpdateRequest $request, Designation $designation): Response
    {
        $designation->update($request->validated());

        return new DesignationResource($designation);
    }

    public function destroy(Request $request, Designation $designation): Response
    {
        $designation->delete();

        return response()->noContent();
    }
}

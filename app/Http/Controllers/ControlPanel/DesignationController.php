<?php

namespace App\Http\Controllers\ControlPanel;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPanel\DesignationStoreRequest;
use App\Http\Requests\ControlPanel\DesignationUpdateRequest;
use App\Http\Resources\ControlPanel\DesignationCollection;
use App\Http\Resources\ControlPanel\DesignationResource;
use App\Models\ControlPanel\Designation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DesignationController extends Controller
{
    public function index(Request $request): DesignationCollection
    {
        $designations = Designation::all();

        return new DesignationCollection($designations);
    }
    
    public function store(DesignationStoreRequest $request): DesignationResource
    {
        $designation = Designation::create($request->validated());

        return new DesignationResource($designation);
    }

    public function show(Request $request, Designation $designation): DesignationResource
    {
        return new DesignationResource($designation);
    }

    public function update(DesignationUpdateRequest $request, Designation $designation): DesignationResource
    {
        $designation->update($request->validated());

        return new DesignationResource($designation);
    }

    public function destroy(Request $request, Designation $designation): DesignationResource
    {
        $designation->delete();

        return response()->noContent();
    }
}

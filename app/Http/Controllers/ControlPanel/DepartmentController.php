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
use Inertia\Inertia;
class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $designations = Designation::query()
            ->when($search, function ($query, $search) {
                $query->where('title', 'like', '%' . $search . '%');
            })
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Departments/Index', [
            'designations' => $designations,
            'filters' => ['search' => $search],
        ]);
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

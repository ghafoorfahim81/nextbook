<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\DepartmentStoreRequest;
use App\Http\Requests\Administration\DepartmentUpdateRequest;
use App\Http\Resources\Administration\DepartmentCollection;
use App\Http\Resources\Administration\DepartmentResource;
use App\Models\Administration\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DepartmentController extends Controller
{
    public function index(Request $request): DepartmentCollection
    {
        $departments = Department::all();

        return new DepartmentCollection($departments);
    }

    public function store(DepartmentStoreRequest $request): DepartmentResource
    {
        $department = Department::create($request->validated());

        return new DepartmentResource($department);
    }

    public function show(Request $request, Department $department): DepartmentResource
    {
        return new DepartmentResource($department);
    }

    public function update(DepartmentUpdateRequest $request, Department $department): DepartmentResource
    {
        $department->update($request->validated());

        return new DepartmentResource($department);
    }

    public function destroy(Request $request, Department $department): DepartmentResource
    {
        $department->delete();

        return response()->noContent();
    }
}

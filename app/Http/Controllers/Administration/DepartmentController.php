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
    public function __construct()
    {
        $this->authorizeResource(Department::class, 'department');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'asc');

        $departments = Department::with(['parent', 'createdBy', 'updatedBy'])
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Administration/Departments/Index', [
            'items' => DepartmentResource::collection($departments),
        ]);
    }

    public function store(DepartmentStoreRequest $request): DepartmentResource
    {
        $department = Department::create($request->validated());

        return new DepartmentResource($department);
    }

    public function show(Request $request, Department $department): DepartmentResource
    {
        $department->load(['parent', 'createdBy', 'updatedBy']);
        return new DepartmentResource($department);
    }

    public function update(DepartmentUpdateRequest $request, Department $department): DepartmentResource
    {
        $department->update($request->validated());

        return new DepartmentResource($department);
    }

    public function destroy(Request $request, Department $department)
    {
        // Check for dependencies before deletion
        if (!$department->canBeDeleted()) {
            $message = $department->getDependencyMessage();
            return inertia('Administration/Departments/Index', [
                'error' => $message
            ]);
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.department')]));
    }

    public function getParents()
    {
        $parents = Department::whereNull('parent_id')->orWhereNotNull('parent_id')->get(['id', 'name']);
        return response()->json($parents);
    }
}

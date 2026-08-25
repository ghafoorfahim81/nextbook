<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\DepartmentStoreRequest;
use App\Http\Requests\Administration\DepartmentUpdateRequest;
use App\Http\Resources\Administration\DepartmentResource;
use App\Models\Administration\Department;
use App\Services\DeletedRecordService;
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
        $sortableFields = ['name', 'code', 'created_at'];
        $sortField = $request->input('sortField', 'name');
        $sortColumn = in_array($sortField, $sortableFields, true) ? $sortField : 'name';
        $sortDirection = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';

        $departments = Department::with(['parent', 'createdBy', 'updatedBy'])
            ->search($request->query('search'))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Administration/Departments/Index', [
            'items' => DepartmentResource::collection($departments),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $sortColumn,
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function store(DepartmentStoreRequest $request)
    {
        Department::create($request->validated());

        return redirect()->route('departments.index')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.department')]));
    }

    public function show(Request $request, Department $department): DepartmentResource
    {
        $department->load(['parent', 'createdBy', 'updatedBy']);
        return new DepartmentResource($department);
    }

    public function update(DepartmentUpdateRequest $request, Department $department)
    {
        $department->update($request->validated());

        return redirect()->route('departments.index')
            ->with('success', __('general.updated_successfully', ['resource' => __('general.resource.department')]));
    }

    public function destroy(Request $request, Department $department)
    {
        // Check for dependencies before deletion
        if (!$department->canBeDeleted()) {
            $message = $department->getDependencyMessage();
            return redirect()->route('departments.index')->with('error', $message);
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.department')]));
    }

    public function restore(Request $request, Department $department)
    {
        $this->authorize('update', $department);

        $department->restore();

        return redirect()->route('departments.index')
            ->with('success', __('general.restored_successfully', ['resource' => __('general.resource.department')]));
    }

    public function forceDelete(Request $request, Department $department)
    {
        $this->authorize('delete', $department);

        app(DeletedRecordService::class)->forceDelete('departments', (string) $department->id);

        return redirect()->route('departments.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.department')]));
    }

    public function getParents()
    {
        $this->authorize('viewAny', Department::class);

        $parents = Department::query()->orderBy('name')->get(['id', 'name']);
        return response()->json($parents);
    }
}

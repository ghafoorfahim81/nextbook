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
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $search = $request->input('search', '');
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'asc');

        $query = Department::with('parent')
            ->when($search, function ($query, $search) {
                return $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy($sortField, $sortDirection);
        dd($query->get();
        $data = $query->paginate($perPage)->withQueryString();

        $departments = Department::with('parent')
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Administration/Departments/Index', [
//            'items' => DepartmentResource::collection($departments),
            'items' => DepartmentResource::collection($data),
            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
                'sortField' => $sortField,
                'sortDirection' => $sortDirection,
            ],
        ]);
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

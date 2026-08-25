<?php

namespace App\Http\Controllers\Administration;

use App\Models\Administration\Department;
use App\Models\Administration\Designation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\DesignationStoreRequest;
use App\Http\Requests\Administration\DesignationUpdateRequest;
use App\Http\Resources\Administration\DesignationResource;
use App\Services\DeletedRecordService;
use Illuminate\Http\Request;

/**
 * Job titles.
 *
 * Rebuilt from a Blueprint API scaffold: every action declared a return type of
 * Illuminate\Http\Response while returning a JsonResource, and index() returned
 * an unpaginated collection with no Inertia page — so the screen could not
 * render at all.
 */
class DesignationController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Designation::class, 'designation');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortableFields = [
            'name' => 'name',
            'code' => 'code',
            'grade_level' => 'grade_level',
        ];
        $sortColumn = $sortableFields[$request->input('sortField', 'name')] ?? 'name';

        $designations = Designation::query()
            ->with(['department:id,name', 'createdBy:id,name', 'updatedBy:id,name'])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Administration/Designations/Index', [
            'items' => DesignationResource::collection($designations),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $request->input('sortField', 'name'),
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function store(DesignationStoreRequest $request)
    {
        Designation::create($request->validated());

        return redirect()->route('designations.index')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.designation')]));
    }

    public function show(Request $request, Designation $designation): DesignationResource
    {
        $designation->load(['department:id,name', 'createdBy:id,name', 'updatedBy:id,name']);

        return new DesignationResource($designation);
    }

    public function update(DesignationUpdateRequest $request, Designation $designation)
    {
        $designation->update($request->validated());

        return redirect()->route('designations.index')
            ->with('success', __('general.updated_successfully', ['resource' => __('general.resource.designation')]));
    }

    public function destroy(Request $request, Designation $designation)
    {
        if (! $designation->canBeDeleted()) {
            return redirect()->route('designations.index')->with('error', $designation->getDependencyMessage());
        }

        $designation->delete();

        return redirect()->route('designations.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.designation')]));
    }

    public function restore(Request $request, Designation $designation)
    {
        $this->authorize('update', $designation);

        $designation->restore();

        return redirect()->route('designations.index')
            ->with('success', __('general.restored_successfully', ['resource' => __('general.resource.designation')]));
    }

    public function forceDelete(Request $request, Designation $designation)
    {
        $this->authorize('delete', $designation);

        app(DeletedRecordService::class)->forceDelete('designations', (string) $designation->id);

        return redirect()->route('designations.index')
            ->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.designation')]));
    }
}

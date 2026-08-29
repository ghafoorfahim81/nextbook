<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\LandedCostCategoryStoreRequest;
use App\Http\Requests\Administration\LandedCostCategoryUpdateRequest;
use App\Http\Resources\Administration\LandedCostCategoryResource;
use App\Models\Administration\LandedCostCategory;
use App\Services\DeletedRecordService;
use Illuminate\Http\Request;

class LandedCostCategoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(LandedCostCategory::class, 'landed_cost_category');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortableFields = [
            'name' => 'name',
        ];
        $sortColumn = $sortableFields[$request->input('sortField', 'name')] ?? 'name';

        $landedCostCategories = LandedCostCategory::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->search($request->query('search'))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Administration/LandedCostCategories/Index', [
            'items' => LandedCostCategoryResource::collection($landedCostCategories),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $request->input('sortField', 'name'),
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function store(LandedCostCategoryStoreRequest $request)
    {
        LandedCostCategory::create($request->validated());

        return redirect()->route('landed-cost-categories.index')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.landed_cost_category')]));
    }

    public function show(Request $request, LandedCostCategory $landedCostCategory): LandedCostCategoryResource
    {
        $landedCostCategory->load(['createdBy:id,name', 'updatedBy:id,name']);

        return new LandedCostCategoryResource($landedCostCategory);
    }

    public function update(LandedCostCategoryUpdateRequest $request, LandedCostCategory $landedCostCategory)
    {
        $landedCostCategory->update($request->validated());

        return redirect()->route('landed-cost-categories.index')
            ->with('success', __('general.updated_successfully', ['resource' => __('general.resource.landed_cost_category')]));
    }

    public function destroy(Request $request, LandedCostCategory $landedCostCategory)
    {
        if (! $landedCostCategory->canBeDeleted()) {
            return redirect()->route('landed-cost-categories.index')->with('error', $landedCostCategory->getDependencyMessage());
        }

        $landedCostCategory->delete();

        return redirect()->route('landed-cost-categories.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.landed_cost_category')]));
    }

    public function restore(Request $request, LandedCostCategory $landedCostCategory)
    {
        $this->authorize('update', $landedCostCategory);

        $landedCostCategory->restore();

        return redirect()->route('landed-cost-categories.index')
            ->with('success', __('general.restored_successfully', ['resource' => __('general.resource.landed_cost_category')]));
    }

    public function forceDelete(Request $request, LandedCostCategory $landedCostCategory)
    {
        $this->authorize('delete', $landedCostCategory);

        app(DeletedRecordService::class)->forceDelete('landed_cost_categories', (string) $landedCostCategory->id);

        return redirect()->route('landed-cost-categories.index')
            ->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.landed_cost_category')]));
    }
}

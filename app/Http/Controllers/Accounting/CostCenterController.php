<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CostCenterRequest;
use App\Models\Accounting\CostCenter;
use Illuminate\Http\Request;

/**
 * Cost centers (phase 7): a list with a create/edit dialog, like expense
 * categories. Where a cost belongs, independent of the branch tree.
 */
class CostCenterController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(CostCenter::class, 'cost_center');
    }

    public function index(Request $request)
    {
        $costCenters = CostCenter::query()
            ->with('parent:id,name')
            ->search($request->query('search'))
            ->orderBy($request->input('sortField', 'name'), $request->input('sortDirection', 'asc'))
            ->paginate($request->input('perPage', recordsPerPage()))
            ->withQueryString();

        return inertia('Accounting/CostCenters/Index', [
            'costCenters' => $costCenters->through(fn (CostCenter $center) => [
                'id' => $center->id,
                'code' => $center->code,
                'name' => $center->name,
                'parent_id' => $center->parent_id,
                'parent' => $center->parent?->name,
                'description' => $center->description,
                'is_active' => $center->is_active,
            ]),
            // Every cost center, for the parent picker.
            'allCostCenters' => CostCenter::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(CostCenterRequest $request)
    {
        CostCenter::create($request->validated());

        return back()->with('success', __('general.created_successfully', ['resource' => __('general.resource.cost_center')]));
    }

    public function show(CostCenter $costCenter)
    {
        return response()->json(['data' => $costCenter]);
    }

    public function update(CostCenterRequest $request, CostCenter $costCenter)
    {
        $costCenter->update($request->validated());

        return back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.cost_center')]));
    }

    public function destroy(CostCenter $costCenter)
    {
        if (! $costCenter->canBeDeleted()) {
            return back()->with('error', $costCenter->getDependencyMessage() ?? __('messages.dependency.cannot_delete_generic'));
        }

        $costCenter->delete();

        return back()->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.cost_center')]));
    }
}

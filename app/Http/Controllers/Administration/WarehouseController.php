<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\WarehouseStoreRequest;
use App\Http\Requests\Administration\WarehouseUpdateRequest;
use App\Http\Resources\Administration\WarehouseResource;
use App\Models\Administration\Warehouse;
use App\Support\Inertia\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Inventory\StockMovement;
class WarehouseController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Warehouse::class, 'warehouse');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $warehouses = Warehouse::with(['createdBy', 'updatedBy'])
            ->search($request->query('search'))
            // ->where('is_active', true)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Administration/Warehouses/Index', [
            'warehouses' => WarehouseResource::collection($warehouses),
        ]);
    }

    public function store(WarehouseStoreRequest $request)
    {
        Warehouse::create($request->validated());

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'warehouses'));

        return redirect()
            ->route('warehouses.index')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.warehouse')]));
    }

    public function show(Request $request, Warehouse $warehouse): WarehouseResource
    {
        $warehouse->load(['createdBy', 'updatedBy']);

        return new WarehouseResource($warehouse);
    }

    public function update(WarehouseUpdateRequest $request, Warehouse $warehouse)
    {
        $warehouse->update($request->validated());

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'warehouses'));

        return redirect()
            ->route('warehouses.index')
            ->with('success', __('general.updated_successfully', ['resource' => __('general.resource.warehouse')]));
    }

    public function destroy(Request $request, Warehouse $warehouse)
    {
        if (!$warehouse->canBeDeleted()) {
            $message = $warehouse->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
            return inertia('Administration/Warehouses/Index', [
                'error' => $message,
            ]);
        }

        if ($warehouse->is_main) {
            return redirect()->route('warehouses.index')->with('error', __('general.cannot_delete_main_warehouse'));
        }
        $stockMovements = StockMovement::where('warehouse_id', $warehouse->id)->first();
        if($stockMovements){
            return redirect()->route('warehouses.index')->with('error', __('general.cannot_delete_warehouse_with_stock_movements', ['resource' => __('general.resource.warehouse')]));
        }

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'warehouses'));

        $warehouse->delete();

        return redirect()
            ->route('warehouses.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.warehouse')]));
    }

    public function restore(Request $request, Warehouse $warehouse)
    {
        $warehouse->restore();

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'warehouses'));

        return redirect()
            ->route('warehouses.index')
            ->with('success', __('general.restored_successfully', ['resource' => __('general.resource.warehouse')]));
    }

    public function forceDelete(Request $request, Warehouse $warehouse)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('warehouses', (string) $warehouse->id);

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'warehouses'));

        return redirect()
            ->route('warehouses.index')
            ->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.warehouse')]));
    }
}

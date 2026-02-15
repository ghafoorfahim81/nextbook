<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\StoreStoreRequest;
use App\Http\Requests\Administration\StoreUpdateRequest;
use App\Http\Resources\Administration\StoreCollection;
use App\Http\Resources\Administration\StoreResource;
use App\Models\Administration\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
class StoreController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Store::class, 'store');
    }

    public function index(Request $request)
    {

        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $stores = Store::with(['createdBy', 'updatedBy'])
            ->search($request->query('search'))
            ->where('is_active', true)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Administration/Stores/Index', [
            'stores' => StoreResource::collection($stores),
        ]);
    }

    public function store(StoreStoreRequest $request)
    {
        $store = Store::create($request->validated());
        Cache::forget('stores');
        return redirect()->route('stores.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.store')]));


    }

    public function show(Request $request, Store $store): StoreResource
    {
        $store->load(['createdBy', 'updatedBy']);
        return new StoreResource($store);
    }

    public function update(StoreUpdateRequest $request, Store $store)
    {
        $store->update($request->validated());
        Cache::forget('stores');
        return redirect()->route('stores.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.store')]));

    }

    public function destroy(Request $request, Store $store)
    {

        // Check for dependencies before deletion
        if (!$store->canBeDeleted()) {
            $message = $store->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
            return inertia('Administration/Stores/Index', [
                'error' => $message
            ]);
        }
        if($store->is_main) {
            return redirect()->route('stores.index')->with('error', __('general.cannot_delete_main_store'));
        }
        Cache::forget('stores');

        $store->delete();
        return redirect()->route('stores.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.store')]));
    }
    public function restore(Request $request, Store $store)
    {
        $store->restore();
        return redirect()->route('stores.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.store')]));
        Cache::forget('stores');
    }
}

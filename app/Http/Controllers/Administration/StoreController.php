<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\StoreStoreRequest;
use App\Http\Requests\Administration\StoreUpdateRequest;
use App\Http\Resources\Administration\StoreCollection;
use App\Http\Resources\Administration\StoreResource;
use App\Models\Administration\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StoreController extends Controller
{
    public function index(Request $request): Response
    {

        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'asc');

        $stores = Store::search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Administration/Departments/Index', [
            'items' => DepartmentResource::collection($stores),
        ]);
    }

    public function store(StoreStoreRequest $request): Response
    {
        $store = Store::create($request->validated());

        return new StoreResource($store);
    }

    public function show(Request $request, Store $store): Response
    {
        return new StoreResource($store);
    }

    public function update(StoreUpdateRequest $request, Store $store): Response
    {
        $store->update($request->validated());

        return new StoreResource($store);
    }

    public function destroy(Request $request, Store $store): Response
    {
        $store->delete();

        return response()->noContent();
    }
}

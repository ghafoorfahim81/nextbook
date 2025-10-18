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
    public function index(Request $request)
    {

        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $stores = Store::search($request->query('search'))
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
        return redirect()->route('stores.index')->with('success', 'Store created successfully.');


    }

    public function show(Request $request, Store $store): Response
    {
        return new StoreResource($store);
    }

    public function update(StoreUpdateRequest $request, Store $store)
    {
        $store->update($request->validated());
        return redirect()->route('stores.index')->with('success', 'Store created successfully.');

    }

    public function destroy(Request $request, Store $store)
    {
        $store->delete();

        return back();
    }
    public function restore(Request $request, Store $store)
    {
        $store->restore();
        return redirect()->route('stores.index')->with('success', 'Store restored successfully.');
    }
}

<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ItemStoreRequest;
use App\Http\Requests\Inventory\ItemUpdateRequest;
use App\Http\Resources\Inventory\ItemCollection;
use App\Http\Resources\Inventory\ItemResource;
use App\Models\Inventory\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $items = Item::with('category','unitMeasure')
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Inventories/Items/Index', [
            'items' => ItemResource::collection($items),
        ]);
    }

    public function create()
    {
        return inertia('Inventories/Items/Create');
    }
    public function store(ItemStoreRequest $request)
    {
        $item = Item::create($request->validated());

        return redirect()->route('items.index')->with('success', 'Items created successfully.');

    }

    public function show(Request $request, Item $item): Response
    {
        return new ItemResource($item);
    }

    public function update(ItemUpdateRequest $request, Item $item): Response
    {
        $item->update($request->validated());

        return new ItemResource($item);
    }

    public function destroy(Request $request, Item $item): Response
    {
        $item->delete();

        return response()->noContent();
    }
}

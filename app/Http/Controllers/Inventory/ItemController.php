<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ItemStoreRequest;
use App\Http\Requests\Inventory\ItemUpdateRequest;
use App\Http\Resources\Inventory\ItemResource;
use App\Models\Inventory\Item;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockOpening;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $items = Item::with('category', 'unitMeasure')
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
        $validated = $request->validated();
        // dd($request->all());
        // If you’re uploading a photo here, handle it first (optional)
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('items', 'public');
            $validated['photo'] = $path;
        }

        DB::transaction(function () use ($validated, $request) {
            // 1) Create item
            $item = Item::create($validated);

            // 2) Create opening stocks (if any)
            $openings = collect($request->input('openings', []));

            $openings
                ->filter(function ($o) {
                    return !empty($o['store_id']) && (float)($o['quantity'] ?? 0) > 0;
                })
                ->each(function ($o) use ($item, $request) {
                    // pick cost source (fallback to purchase_price or cost on item form)
                    $cost = (float)($request->input('cost') ?? $request->input('purchase_price') ?? 0);

                    // create stock
                    $stock = Stock::create([
                        'id'              => (string) Str::ulid(),
                        'item_id'         => $item->id,
                        'store_id'        => $o['store_id'],
                        'unit_measure_id' => $request->input('unit_measure_id'), // from item form
                        'quantity'        => (float) $o['quantity'],
                        'cost'            => $cost,
                        'free'            => isset($o['free']) ? (float) $o['free'] : null,
                        'batch'           => $o['batch'] ?? null,
                        'discount'        => isset($o['discount']) ? (float) $o['discount'] : null,
                        'tax'             => isset($o['tax']) ? (float) $o['tax'] : null,
                        'date'            => $o['date'] ?? Carbon::now()->toDateString(),
                        'expire_date'     => $o['expire_date'] ?? null,
                        'purchase_id'     => null, // opening, not a purchase
                    ]);

                    // mark it as an opening
                    StockOpening::create([
                        'id'      => (string) Str::ulid(),
                        'item_id' => $item->id,
                        'stock_id' => $stock->id,
                    ]);
                });
        });
        if ((bool) $request->input('stay') || (bool) $request->input('create_and_new')) {
            return redirect()->route('items.create')->with('success', 'Item created successfully.');
        }
        return redirect()->route('items.index')->with('success', 'Items created successfully.');
    }

    public function show(Request $request, Item $item)
    {
        return new ItemResource($item);
    }

    public function edit(Request $request, Item $item)
    {
        $item = Item::with('unitMeasure', 'brand', 'category')->find($item->id);
        return inertia('Inventories/Items/Edit', [
            'item' => new ItemResource($item)
        ]);
    }

    public function update(ItemUpdateRequest $request, Item $item)
    {
        $validated = $request->validated();

        // Handle photo update
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('items', 'public');
            $validated['photo'] = $path;
        }

        DB::transaction(function () use ($validated, $request, $item) {
            // 1) Update item
            $item->update($validated);

            // 2) Handle openings
            $openings = collect($request->input('openings', []));

            // Remove old openings (optional: you may also soft-delete instead)
            $item->openings->each(function ($opening) {
                $opening->delete();
                $opening->stock()->delete();
            });
            $openings
                ->filter(fn($o) => !empty($o['store_id']) && (float)($o['quantity'] ?? 0) > 0)
                ->each(function ($o) use ($item, $request) {
                    $cost = (float)($request->input('cost') ?? $request->input('purchase_price') ?? 0);

                    $stock = Stock::create([
                        'item_id' => $item->id,
                        'store_id' => $o['store_id'],
                        'unit_measure_id' => $request->unit_measure_id,
                        'quantity' => (float) $o['quantity'],
                        'cost' => $cost,
                        'free' => isset($o['free']) ? (float) $o['free'] : null,
                        'batch' => $o['batch'] ?? null,
                        'discount' => isset($o['discount']) ? (float) $o['discount'] : null,
                        'tax' => isset($o['tax']) ? (float) $o['tax'] : null,
                        'date' => $o['date'] ?? now()->toDateString(),
                        'expire_date' => $o['expire_date'] ?? null,
                        'purchase_id' => null,
                    ]);

                    $stock->opening()->create([
                        'item_id' => $item->id,
                        'stock_id' => $stock->id,
                    ]);
                });
        });

        return redirect()->route('items.index')->with('success', 'Item updated successfully.');
    }


    public function destroy(Request $request, Item $item)
    {
        $item->openings()->delete();
        $item->stocks()->delete();
        $item->delete();
        return back();
    }
}

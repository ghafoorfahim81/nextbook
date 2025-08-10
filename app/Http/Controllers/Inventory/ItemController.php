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
        $validated = $request->validated();

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
                    // only rows with a store and quantity > 0
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
                        'stock_id'=> $stock->id,
                    ]);
                });
        });
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

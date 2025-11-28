<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ItemStoreRequest;
use App\Http\Requests\Inventory\ItemUpdateRequest;
use App\Http\Resources\Inventory\ItemResource;
use App\Models\Inventory\Item;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockOpening;
use App\Models\Inventory\StockOut;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Http\Resources\Inventory\StockResource;
use App\Http\Resources\Inventory\StockOutResource;
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
        // Get the maximum code as integer (cast to handle mixed formats like "3" and "004")
        $maxCode = Item::query()->selectRaw('MAX(CAST(code AS INTEGER)) as max_code')->value('max_code');
        $maxCode = $maxCode ? intval($maxCode) + 1 : 1;

        return inertia('Inventories/Items/Create', [
            'maxCode' => $maxCode,
        ]);
    }
    public function store(ItemStoreRequest $request)
    {
        $validated = $request->validated();
        // dd($request->all());
        // If you're uploading a photo here, handle it first (optional)
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('items', 'public');
            $validated['photo'] = $path;
        }

        DB::transaction(function () use ($validated, $request) {
            // 1) Create item
            $item = Item::create($validated);
            // 2) Create opening stocks (if any)
            $openings = collect($request->input('openings', []));
            $dateConversionService = app(\App\Services\DateConversionService::class);

            $openings
                ->filter(function ($o) {
                    return !empty($o['store_id']) && (float)($o['quantity'] ?? 0) > 0;
                })
                ->each(function ($o) use ($item, $request, $dateConversionService) {
                    // pick cost source (fallback to purchase_price or cost on item form)
                    $cost = (float)($request->input('cost') ?? $request->input('purchase_price') ?? 0);
                    $expire_date = $dateConversionService->toGregorian($o['expire_date']);
                    $date = $dateConversionService->toGregorian($o['date'] ?? Carbon::now()->toDateString());
                    // create stock
                    $stockService = app(\App\Services\StockService::class);
                    $stock = $stockService->addStock([
                        'item_id' => $item->id,
                        'store_id' => $o['store_id'],
                        'unit_measure_id' => $request->input('unit_measure_id'), // from item form
                        'quantity'        => (float) $o['quantity'],
                        'unit_price'      => $cost,
                        'free'            => isset($o['free']) ? (float) $o['free'] : null,
                        'batch'           => $o['batch'] ?? null,
                        'date'            => $date,
                        'expire_date'     => $expire_date,
                    ], $o['store_id'], 'opening', $item->id);

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
        // $item->load(['stock_count', 'stock_out_count']);
        return inertia('Inventories/Items/Show', [
            'item' => new ItemResource($item),
        ]);
    }
    public function inRecords(Request $request, Item $item)
    {
        $stocks = Stock::with(['store', 'unitMeasure', 'source.supplier'])
            ->where('item_id', $item->id)
            ->orderBy('date', 'desc')
            ->paginate($request->input('per_page', 10));

        return response()->json([
            'data' => StockResource::collection($stocks),
            'meta' => [
                'current_page' => $stocks->currentPage(),
                'last_page' => $stocks->lastPage(),
                'per_page' => $stocks->perPage(),
                'total' => $stocks->total(),
            ],
        ]);
    }

    public function outRecords(Request $request, Item $item)
    {
        $stockOuts = StockOut::with(['store', 'unitMeasure', 'source.customer'])
            ->where('item_id', $item->id)
            ->orderBy('date', 'desc')
            ->paginate($request->input('per_page', 10));

        return response()->json([
            'data' => StockOutResource::collection($stockOuts),
            'meta' => [
                'current_page' => $stockOuts->currentPage(),
                'last_page' => $stockOuts->lastPage(),
                'per_page' => $stockOuts->perPage(),
                'total' => $stockOuts->total(),
            ],
        ]);
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
            $dateConversionService = app(\App\Services\DateConversionService::class);
            // Remove old openings (optional: you may also soft-delete instead)
            $item->openings->each(function ($opening) {
                $opening->forceDelete();
                $opening->stock()->forceDelete();
            });
            $openings
                ->filter(fn($o) => !empty($o['store_id']) && (float)($o['quantity'] ?? 0) > 0)
                ->each(function ($o) use ($item, $request, $dateConversionService) {
                    $cost = (float)($request->input('cost') ?? $request->input('purchase_price') ?? 0);
                    $expire_date = $dateConversionService->toGregorian($o['expire_date']);
                    $date = $dateConversionService->toGregorian($o['date'] ?? Carbon::now()->toDateString());
                    $stockService = app(\App\Services\StockService::class);
                    $stock = $stockService->addStock([
                        'item_id' => $item->id,
                        'store_id' => $o['store_id'],
                        'unit_measure_id' => $request->input('unit_measure_id'), // from item form
                        'quantity'        => (float) $o['quantity'],
                        'unit_price'      => $cost,
                        'free'            => isset($o['free']) ? (float) $o['free'] : null,
                        'batch'           => $o['batch'] ?? null,
                        'date'            => $date,
                        'expire_date'     => $expire_date,
                    ], $o['store_id'], 'opening', $item->id);

                    StockOpening::create([
                        'id'      => (string) Str::ulid(),
                        'item_id' => $item->id,
                        'stock_id' => $stock->id,
                    ]);
                });
        });

        return redirect()->route('items.index')->with('success', 'Item updated successfully.');
    }


    public function destroy(Request $request, Item $item)
    {
        // Check for dependencies before deletion
        if (!$item->canBeDeleted()) {
            $message = $item->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
            return inertia('Inventories/Items/Index', [
                'error' => $message
            ]);
        }

        foreach ($item->openings as $opening) {
            $opening->stock()->delete();
        }
        $item->openings()->delete();
        $item->delete();
        return redirect()->route('items.index')->with('success', 'Item deleted successfully.');
    }
    public function restore(Request $request, Item $item)
    {
        $item->restore();
        foreach ($item->openings as $opening) {
            $opening->stock()->restore();
        }
        $item->openings()->restore();
        return redirect()->route('items.index')->with('success', 'Item restored successfully.');
    }
}

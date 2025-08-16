<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\FastOpeningRequest;
use App\Models\Administration\Store;
use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockOpening;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FastOpeningController extends Controller
{
    public function index()
    {
        // Fetch all items with their related units and other necessary details
        $items = Item::with('unitMeasure') // eager load unitMeasure and stores (if necessary)
        ->get();
        return Inertia::render('Inventories/Items/FastOpening', [
            'items'        => $items
        ]);
    }

    public function Store(FastOpeningRequest $request)
    {
        $validated = $request->validated();
        $rows = collect($validated['items']);
        DB::transaction(function () use ($rows) {
            foreach ($rows as $itemData) {

                // Check if item already has an opening
                $existingOpening = StockOpening::where('item_id', $itemData['item_id'])->first();

                if ($existingOpening) {
                    // Update existing stock
                    $stock = $existingOpening->stock;
                    $stock->update([
                        'quantity'        => $itemData['quantity'],
                        'unit_measure_id' => $itemData['unit_measure_id'],
                        'expire_date'     => $itemData['expire_date'] ?? null,
                        'batch'           => $itemData['batch'] ?? null,
                        'cost'            => $itemData['cost'] ?? null,
                        'store_id'        => $itemData['store_id']??Store::where('is_main',true)->first()->id,
                    ]);

                    // Ensure opening item_id is correct
                    $existingOpening->update(['item_id' => $itemData['item_id']]);
                } else {
                    // Create new stock + opening
                    $stock = Stock::create([
                        'item_id'         => $itemData['item_id'],
                        'quantity'        => $itemData['quantity'],
                        'unit_measure_id' => $itemData['unit_measure_id'],
                        'expire_date'     => $itemData['expire_date'] ?? null,
                        'batch'           => $itemData['batch'] ?? null,
                        'cost'            => $itemData['cost'] ?? null,
                        'store_id'        => $itemData['store_id']??Store::where('is_main',true)->first()->id,
                    ]);

                    $stock->opening()->create([
                        'item_id' => $itemData['item_id']
                    ]);
                }
            }

        });
    }
}

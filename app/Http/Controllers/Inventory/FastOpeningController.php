<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\FastOpeningRequest;
use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;
use App\Models\Inventory\Stock;
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

        $unitMeasures = UnitMeasure::all(); // Fetch all unit measures available

        return Inertia::render('Inventories/Items/FastOpening', [
            'items'        => $items
        ]);
    }

    public function Store(FastOpeningRequest $request)
    {
        return $request->all();
        $validated = $request->validated();
        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $itemData) {
                // 1) Create stock record
                $stock = Stock::create([
                    'item_id'         => $itemData['item_id'],
                    'quantity'        => $itemData['quantity'],
                    'unit_measure_id' => $itemData['unit_measure_id'],
                    'expire_date'     => $itemData['expire_date'] ?? null,
                    'purchase_price'   => $itemData['purchase_price'] ?? null,
                    'store_id'        => $itemData['store_id'],
                ]);

                // 2) Create StockOpening
                $stock->opening->create([
                    'item_id'         => $stock->item_id,
                ]);
            }
        });
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Administration\Store;
use App\Models\Inventory\Item;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockOut;

class NextController extends Controller
{
    public function purchaseItemChange(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'string'],
            'store_id' => ['required', 'string'],
        ]);

        $itemId = $validated['item_id'];
        $storeId = $validated['store_id'];

        $item = DB::table('items')->where('id', $itemId)->first();
        $storeExists = DB::table('stores')->where('id', $storeId)->exists();
        if (!$item || !$storeExists) {
            return response()->json(['message' => 'Item or store not found'], 404);
        }

        // Compute on hand: sum(stocks.quantity) - sum(stock_outs.qut_out)
        $stockIn = DB::table('stocks')
            ->where('item_id', $itemId)
            ->where('store_id', $storeId)
            ->sum('quantity');

        $stockOut = DB::table('stock_outs')
            ->where('item_id', $itemId)
            ->where('store_id', $storeId)
            ->sum('qut_out');

        $onHand = (float)$stockIn - (float)$stockOut;

        // Measure unit from item default unit
        $measure = DB::table('unit_measures')->select('id', 'symbol', 'name')->where('id', $item->unit_measure_id)->first();

        // Purchase price rule: use items.purchase_price
        $purchasePrice = (float)($item->purchase_price ?? 0);

        return response()->json([
            'itemId' => $itemId,
            'storeId' => $storeId,
            'onHand' => (float)$onHand,
            'measure' => $measure,
            'purchasePrice' => $purchasePrice,
        ]);
    }

    public function getItemWithBatches(Request $request)
    {
        $storeId = $request->store_id??Store::main()->id;
        $itemId  = $request->item_id;
        $items   = Item::where('id', $itemId)->get();
        if (!$items) {
           $items = Item::get()->limit(10);
           $items = $items->map(function ($item) use ($storeId) {
            $batchesIn = Stock::where('item_id', $item->id)
                ->where('store_id', $storeId)
                ->groupBy('batch')
                ->get();
            if($batchesIn){
                $batchesOut = StockOut::where('item_id', $item->id)
                    ->where('store_id', $storeId)
                    ->get()
                    ->groupBy('batch');

                $batches = $batchesIn->map(function ($batch, $batchKey) use ($batchesOut) {
                    $quantityIn = $batch->sum('quantity');
                    $quantityOut = $batchesOut->get($batchKey, collect())->sum('quantity');
                    return [
                        'batch' => $batchKey,
                        'expire_date' => $batch->first()->expire_date,
                        'quantity' => $quantityIn - $quantityOut,
                    ];
                })->values();
            }

            return [
                'id' => $item->id,
                'name' => $item->name,
                'barcode' => $item->barcode,
                'unit_measure_id' => $item->unit_measure_id,
                'unitMeasure'  => $item->unitMeasure,
                'brand' => $item->brand,
                'colors' => $item->colors,
                'size' => $item->size,
                'purchase_price' => $item->purchase_price,
                'cost' => $item->cost,
                'mrp_rate' => $item->mrp_rate,
                'rate_a' => $item->rate_a,
                'rate_b' => $item->rate_b,
                'rate_c' => $item->rate_c,
                'rack_no' => $item->rack_no,
                'fast_search' => $item->fast_search,
                'quantity' => $item->stocks->sum(('quantity')),
        ];
           });

        }

        return response()->json($items);
    }
}

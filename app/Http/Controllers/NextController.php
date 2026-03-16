<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Administration\Warehouse;
use App\Models\Inventory\Item;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockOut;

class NextController extends Controller
{
    public function purchaseItemChange(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'string'],
            'warehouse_id' => ['required', 'string'],
        ]);

        $itemId = $validated['item_id'];
        $warehouseId = $validated['warehouse_id'];

        $item = DB::table('items')->where('id', $itemId)->first();
        $warehouseExists = DB::table('warehouses')->where('id', $warehouseId)->exists();
        if (!$item || !$warehouseExists) {
            return response()->json(['message' => 'Item or warehouse not found'], 404);
        }

        // Compute on hand: sum(stocks.quantity) - sum(stock_outs.qut_out)
        $stockIn = DB::table('stocks')
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');

        $stockOut = DB::table('stock_outs')
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');

        $onHand = (float)$stockIn - (float)$stockOut;

        // Measure unit from item default unit
        $measure = DB::table('unit_measures')->select('id', 'symbol', 'name')->where('id', $item->unit_measure_id)->first();

        // Purchase price rule: use items.purchase_price
        $purchasePrice = (float)($item->purchase_price ?? 0);

        return response()->json([
            'itemId' => $itemId,
            'warehouseId' => $warehouseId,
            'onHand' => (float)$onHand,
            'measure' => $measure,
            'purchasePrice' => $purchasePrice,
        ]);
    }

    public function getItemWithBatches(Request $request)
    {
        $warehouseId = $request->warehouse_id ?? Warehouse::main()->id;
        $itemId  = $request->item_id;
        $items   = Item::where('id', $itemId)->get();
        $items = $this->mapItem($items, $warehouseId);
        if (!$items->count()>0) {
            $items = Item::limit(10)->get();
           $items = $this->mapItem($items, $warehouseId);
        }

        return $items;
    }

    public function mapItem($items, $warehouseId)
    {
        return $items->map(function ($item) use ($warehouseId) {
            $batchesIn = Stock::where('item_id', $item->id)
                ->where('warehouse_id', $warehouseId)
                ->groupBy('batch','id')
                ->get();
            $batches = [];
            $onHand = 0;
            if($batchesIn){
                $batchesOut = StockOut::where('item_id', $item->id)
                    ->where('warehouse_id', $warehouseId)
                    ->groupBy('batch','id')
                    ->get();

                $batches = $batchesIn->map(function ($batch, $batchKey) use ($batchesOut) {
                    $quantityIn = $batch->sum('quantity');
                    $quantityOut = $batchesOut->get($batchKey, collect())->sum('quantity');
                    return [
                        'batch' => $batchKey,
                        'expire_date' => $batch->first()->expire_date,
                        'quantity' => $quantityIn - $quantityOut,
                    ];
                })->values();
                $onHand = $batchesIn->sum('quantity') - $batchesOut->sum('quantity');
            } else {
                $batches = [];
                $onHand = $item->stocks->where('warehouse_id', $warehouseId)->sum('quantity') - $item->stockOut->where('warehouse_id', $warehouseId)->sum('quantity');
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
                'unit_price' => $item->stocks->where('warehouse_id', $warehouseId)->avg('unit_price'),
                'cost' => $item->cost,
                'sale_price' => $item->sale_price,
                'rate_a' => $item->rate_a,
                'rate_b' => $item->rate_b,
                'rate_c' => $item->rate_c,
                'rack_no' => $item->rack_no,
                'fast_search' => $item->fast_search,
                'onHand' => $onHand,
                'batches' => $batches,
        ];
           });
    }
}

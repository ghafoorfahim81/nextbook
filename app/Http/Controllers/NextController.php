<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
}

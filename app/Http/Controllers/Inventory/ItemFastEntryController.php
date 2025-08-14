<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\FastEntryRequest;
use App\Http\Requests\Inventory\ItemStoreRequest;
use App\Models\Inventory\Item;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockOpening;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ItemFastEntryController extends Controller
{

    public function create()
    {
        return inertia('Inventories/Items/FasEntry'); // no “t”
    }

    public function store(FastEntryRequest  $request)
    {
//        return $request->all();
        $validated = $request->validated();
        $rows = collect($validated['items']);

        DB::transaction(function () use ($rows) {
            $today = Carbon::now()->toDateString();

            $rows->each(function ($r) use ($today) {
                // 1) Create the item
                $item = Item::create([
                    // Adjust fields to match your Item fillables/columns
                    'id'             => (string) Str::ulid(), // if your Item uses ULIDs; remove if auto-increment
                    'name'           => $r['name'],
                    'code'           => $r['code'] ?? null,
                    'unit_measure_id'=> $r['measure_id'],     // align with your schema
                    'purchase_price' => $r['purchase_price'] ?? null,
                    'mrp_rate'       => $r['mrp_rate'] ?? null,
                    // add any other default columns your Item requires
                ]);

                // 2) Opening stock (only when store & qty present)
                $qty = (float) ($r['quantity'] ?? 0);
                if (!empty($r['store_id']) && $qty > 0) {
                    $cost = (float) ($r['purchase_price'] ?? 0);

                    $stock = Stock::create([
                        'id'              => (string) Str::ulid(), // if your Stock uses ULIDs
                        'item_id'         => $item->id,
                        'store_id'        => $r['store_id'],
                        'unit_measure_id' => $r['measure_id'],
                        'quantity'        => $qty,
                        'cost'            => $cost,
                        'free'            => null,
                        'batch'           => $r['batch'] ?? null,
                        'discount'        => null,
                        'tax'             => null,
                        'date'            => $today,
                        'expire_date'     => $r['expire_date'] ?? null,
                        'purchase_id'     => null, // opening
                    ]);

                    StockOpening::create([
                        'id'       => (string) Str::ulid(), // if your model uses ULIDs
                        'item_id'  => $item->id,
                        'stock_id' => $stock->id,
                    ]);
                }
            });
        });

        return back()->with('success', 'Items saved successfully.');
    }


}

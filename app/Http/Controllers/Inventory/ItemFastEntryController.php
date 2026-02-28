<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\FastEntryRequest;
use App\Models\Inventory\Item; 
use App\Models\Inventory\StockOpening;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str; 
use App\Enums\ItemType; 
class ItemFastEntryController extends Controller
{

    public function create()
    {
        // Get the maximum code as integer (cast to handle mixed formats like "3" and "004")
        $maxCode = Item::query()->selectRaw('MAX(CAST(code AS INTEGER)) as max_code')->value('max_code');
        $maxCode = $maxCode ? intval($maxCode) + 1 : 1;

        return inertia('Inventories/Items/FasEntry',[
            'maxCode' => $maxCode,
        ]);
    }

    public function store(FastEntryRequest  $request)
    { 
        $validated = $request->validated();
        $rows = collect($validated['items']);
        DB::transaction(function () use ($rows) {
            $today = Carbon::now()->toDateString();

            $rows->each(function ($r) use ($today) {
                // 1) Create the item  
                $glAccounts = Cache::get('gl_accounts');
                $item = Item::create([
                    // Adjust fields to match your Item fillables/columns
                    'id'             => (string) Str::ulid(), // if your Item uses ULIDs; remove if auto-increment
                    'name'           => $r['name'],
                    'item_type'      => ItemType::INVENTORY_MATERIALS->value,
                    'asset_account_id' => $glAccounts['inventory-stock'],
                    'income_account_id' => $glAccounts['product-income'],
                    'cost_account_id' => $glAccounts['cost-of-goods-sold'],
                    'code'           => $r['code'] ?? null,
                    'unit_measure_id'=> $r['measure_id'],     // align with your schema
                    'purchase_price' => $r['purchase_price'] ?? null,
                    'sale_price'     => $r['sale_price'] ?? null,
                    // add any other default columns your Item requires
                ]);

                // 2) Opening stock (only when warehouse & qty present)
                $qty = (float) ($r['quantity'] ?? 0);
                if (!empty($r['warehouse_id']) && $qty > 0) {
                    $cost = (float) ($r['purchase_price'] ?? 0);
                    $dateConversionService = app(\App\Services\DateConversionService::class);
                    $expire_date = $r['expire_date']?$dateConversionService->toGregorian($r['expire_date']):null;
                    $date = $dateConversionService->toGregorian(Carbon::now()->toDateString());
                    $transactionService = app(\App\Services\TransactionService::class);
                    $stockService = app(\App\Services\StockService::class);
                    $stock = $stockService->addStock([
                        'item_id' => $item->id,
                        'unit_measure_id' => $r['measure_id'],
                        'quantity' => $qty,
                        'unit_price' => $cost,
                        'free' => null,
                        'batch' => $r['batch'] ?? null,
                        'discount' => null,
                        'tax' => null,
                        'date' => $date,
                        'expire_date' => $expire_date,
                    ], $r['warehouse_id'], 'opening', $item->id, $date);

                    StockOpening::create([
                        'id'      => (string) Str::ulid(),
                        'item_id'  => $item->id,
                        'stock_id' => $stock->id,
                    ]);

                    $cost = (float)($r['purchase_price'] ?? 0);
                    $quantity = (float)($r['quantity'] ?? 0);
                    $glAccounts      = Cache::get('gl_accounts');
                    $homeCurrency = Cache::get('home_currency');
                    $transaction = $transactionService->post(
                        header: [
                            'currency_id' => $homeCurrency->id,
                            'rate' => 1,
                            'date' => $date,
                            'reference_type' => Item::class,
                            'reference_id' => $item->id,
                            'remark' => 'Opening balance for item ' . $item->name,
                        ],
                        lines: [
                            [
                                'account_id' => $glAccounts['inventory-stock'],
                                'debit' => $cost*$quantity, 
                                'credit' => 0,
                            ],
                            [
                                'account_id' => $glAccounts['opening-balance-equity'],
                                'debit' => 0,
                                'credit' => $cost*$quantity,
                            ]
                        ]
                    ); 
                }
            });
        });

        return back()->with('success', __('general.items_saved_successfully'));
    }


}

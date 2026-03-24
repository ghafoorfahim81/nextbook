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
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
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
                    'is_batch_tracked' => $r['batch']?true:false,
                    'is_expiry_tracked' => $r['expire_date']?true:false,
                    // add any other default columns your Item requires
                ]);

                // 2) Opening stock (only when warehouse & qty present)
                $qty = (float) ($r['quantity'] ?? 0);
                if (!empty($r['warehouse_id']) && $qty > 0) {
                    $cost = (float) ($r['purchase_price'] ?? 0);
                    $transactionService = app(\App\Services\TransactionService::class);
                    $stockService = app(\App\Services\StockService::class);


                    $stock = $stockService->post([
                        'item_id'         => $item->id,
                        'movement_type'   => StockMovementType::IN->value,
                        'unit_measure_id' => $r['measure_id'], // from item form
                        'quantity'        => (float) $r['quantity'],
                        'source'          => StockSourceType::OPENING->value,
                        'unit_cost'       => (float) $r['purchase_price'],
                        'status'          => StockStatus::DRAFT->value,
                        'batch'           => $r['batch'] ?? null,
                        'date'            => Carbon::now()->toDateString(),
                        'expire_date'     => $r['expire_date'] ?? null,
                        'size_id'         => $r['size_id'] ?? null,
                        'warehouse_id'    => $r['warehouse_id'],
                        'branch_id'       => auth()->user()->company->branch_id,
                    ]);

                    $cost = (float)($r['purchase_price'] ?? 0);
                    $quantity = (float)($r['quantity'] ?? 0);
                    $glAccounts      = Cache::get('gl_accounts');
                    $homeCurrency = Cache::get('home_currency');
                    $transaction = $transactionService->post(
                        header: [
                            'currency_id' => $homeCurrency->id,
                            'rate' => 1,
                            'date' => Carbon::now()->toDateString(),
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

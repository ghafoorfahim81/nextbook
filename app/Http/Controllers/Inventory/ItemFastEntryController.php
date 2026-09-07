<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\FastEntryRequest;
use App\Models\Inventory\Item;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Enums\ItemType;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Support\BranchContext;
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
            $rows->each(function ($r) {
                // 1) Create the item
                $glAccounts = BranchContext::glAccounts();
                $item = Item::create([
                    'name'           => $r['name'],
                    'item_type'      => ItemType::INVENTORY_MATERIALS->value,
                    'asset_account_id' => $glAccounts['inventory-stock'] ?? null,
                    'income_account_id' => $glAccounts['product-income'] ?? null,
                    'cost_account_id' => $glAccounts['cost-of-goods-sold'] ?? null,
                    'code'           => $r['code'] ?? null,
                    'barcode'        => $r['barcode'] ?? null,
                    'unit_measure_id'=> $r['measure_id'],
                    'purchase_price' => $r['purchase_price'] ?? null,
                    'sale_price'     => $r['sale_price'] ?? null,
                    'is_batch_tracked' => ! empty($r['batch']),
                    'is_expiry_tracked' => ! empty($r['expire_date']),
                ]);

                // Every item carries at least one variant — SKU, barcode and
                // price live there, not on the item.
                app(\App\Services\ItemVariantService::class)->ensureDefault($item);

                // 2) Opening stock (only when warehouse & qty present)
                $qty = (float) ($r['quantity'] ?? 0);
                if (!empty($r['warehouse_id']) && $qty > 0) {
                    $cost = (float) ($r['purchase_price'] ?? 0);
                    $transactionService = app(\App\Services\TransactionService::class);
                    $stockService = app(\App\Services\StockService::class);


                    $stockService->post([
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
                        'warehouse_id'    => $r['warehouse_id'],
                        'branch_id'       => $item->branch_id,
                    ]);

                    $cost = (float)($r['purchase_price'] ?? 0);
                    $quantity = (float)($r['quantity'] ?? 0);
                    $glAccounts      = BranchContext::glAccounts();
                    $homeCurrency = BranchContext::homeCurrency();
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
                                'remark' => 'Opening balance for item ' . ' ' . $item->name,
                                'remark_fa' => 'موجودی اولیه برای جنس ' . ' ' . $item->name,
                                'remark_ps' =>'د'. ' '. $item->name.' '.'د پرانیستلو بیلانس ',
                            ],
                            [
                                'account_id' => $glAccounts['opening-balance-equity'],
                                'debit' => 0,
                                'credit' => $cost*$quantity,
                                'remark' => 'Opening balance for item ' . ' ' . $item->name,
                                'remark_fa' => 'موجودی اولیه برای جنس ' . ' ' . $item->name,
                                'remark_ps' =>'د'. ' '. $item->name.' '.'د پرانیستلو بیلانس ',
                            ]
                        ]
                    );
                }
            });
        });

        return back()->with('success', __('general.items_saved_successfully'));
    }


}

<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\FastEntryRequest;
use App\Models\Inventory\Item;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Enums\ItemType;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Support\BranchContext;
use App\Support\BusinessProfile;

class ItemFastEntryController extends Controller
{

    public function create()
    {
        return inertia('Inventories/Items/FasEntry', [
            'maxCode' => Item::nextCodeNumber(),
            // Resolved fresh here rather than read from the cached `business_profile`
            // shared prop, so a just-changed trade decides the batch/expiry columns
            // on the next load — same as the full item form.
            'businessProfile' => BusinessProfile::current()->toArray(),
        ]);
    }

    public function store(FastEntryRequest  $request)
    {
        $validated = $request->validated();
        $rows = collect($validated['items']);

        DB::transaction(function () use ($rows) {
            $glAccounts = BranchContext::glAccounts();
            $homeCurrency = BranchContext::homeCurrency();
            $transactionService = app(\App\Services\TransactionService::class);
            $stockService = app(\App\Services\StockService::class);

            $rows->each(function ($r) use ($glAccounts, $homeCurrency, $transactionService, $stockService) {
                $purchasePrice = $this->toAmount($r['purchase_price'] ?? null);
                // "Final cost" is what the opening layer is costed at. Blank means
                // the operator only knew the purchase price, so fall back to it.
                $unitCost = $this->toAmount($r['cost'] ?? null) ?? $purchasePrice ?? 0.0;

                // 1) Create the item
                $item = Item::create([
                    'name'           => $r['name'],
                    'item_type'      => ItemType::INVENTORY_MATERIALS->value,
                    'asset_account_id' => $glAccounts['inventory-stock'] ?? null,
                    'income_account_id' => $glAccounts['product-income'] ?? null,
                    'cost_account_id' => $glAccounts['cost-of-goods-sold'] ?? null,
                    'code'           => $r['code'] ?? null,
                    'barcode'        => $r['barcode'] ?? null,
                    'unit_measure_id'=> $r['measure_id'],
                    'category_id'    => $r['category_id'] ?? null,
                    'brand_id'       => $r['brand_id'] ?? null,
                    'purchase_price' => $purchasePrice,
                    'sale_price'     => $this->toAmount($r['sale_price'] ?? null),
                    'cost'           => $unitCost,
                    'is_batch_tracked' => ! empty($r['batch']),
                    'is_expiry_tracked' => ! empty($r['expire_date']),
                ]);

                // Every item carries at least one variant — SKU, barcode and
                // price live there, not on the item.
                $variant = app(\App\Services\ItemVariantService::class)->ensureDefault($item);

                // 2) Opening stock (only when warehouse & qty present)
                $quantity = (float) ($r['quantity'] ?? 0);

                if (empty($r['warehouse_id']) || $quantity <= 0) {
                    return;
                }

                $stockService->post([
                    'item_id'         => $item->id,
                    // Without this the movement and its balance row would carry a
                    // null variant_id while every other entry point sets one.
                    'variant_id'      => $variant->id,
                    'movement_type'   => StockMovementType::IN->value,
                    'unit_measure_id' => $r['measure_id'], // from item form
                    'quantity'        => $quantity,
                    'source'          => StockSourceType::OPENING->value,
                    'unit_cost'       => $unitCost,
                    'status'          => StockStatus::DRAFT->value,
                    'batch'           => $r['batch'] ?? null,
                    'date'            => Carbon::now()->toDateString(),
                    'expire_date'     => $r['expire_date'] ?? null,
                    'warehouse_id'    => $r['warehouse_id'],
                    'branch_id'       => $item->branch_id,
                ]);

                $openingValue = round($unitCost * $quantity, 4);

                // A zero-value opening (quantity but no cost yet) would post a
                // balanced-at-nothing voucher that only clutters the ledger.
                if ($openingValue <= 0) {
                    return;
                }

                $transactionService->post(
                    header: [
                        'currency_id' => $homeCurrency->id,
                        'rate' => 1,
                        'voucher_number' => 'Opening Balance ' . $item->name . ' #' . $item->code,
                        'date' => Carbon::now()->toDateString(),
                        'reference_type' => Item::class,
                        'reference_id' => $item->id,
                        'remark' => 'Opening balance for item ' . $item->name,
                    ],
                    lines: [
                        [
                            'account_id' => $item->asset_account_id ?? $glAccounts['inventory-stock'],
                            'debit' => $openingValue,
                            'credit' => 0,
                            'remark' => 'Opening balance for item ' . ' ' . $item->name,
                            'remark_fa' => 'موجودی اولیه برای جنس ' . ' ' . $item->name,
                            'remark_ps' =>'د'. ' '. $item->name.' '.'د پرانیستلو بیلانس ',
                        ],
                        [
                            'account_id' => $glAccounts['opening-balance-equity'],
                            'debit' => 0,
                            'credit' => $openingValue,
                            'remark' => 'Opening balance for item ' . ' ' . $item->name,
                            'remark_fa' => 'موجودی اولیه برای جنس ' . ' ' . $item->name,
                            'remark_ps' =>'د'. ' '. $item->name.' '.'د پرانیستلو بیلانس ',
                        ]
                    ]
                );
            });
        });

        return back()->with('success', __('general.items_saved_successfully'));
    }

    /**
     * Money coming off the grid arrives as '' for an untouched cell. Casting that
     * straight to float would store 0 and make "not priced yet" look like "free".
     */
    private function toAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}

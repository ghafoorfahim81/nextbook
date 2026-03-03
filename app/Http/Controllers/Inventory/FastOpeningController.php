<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\FastOpeningRequest;
use App\Models\Administration\Warehouse;
use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;
use App\Enums\StockMovementType;
use App\Enums\StockStatus;
use App\Enums\StockSourceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Services\TransactionService;
class FastOpeningController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());

        // Fetch items with their related units and other necessary details
        $items = Item::with('unitMeasure')
            ->whereDoesntHave('openings') // Only items with NO opening
            ->orderBy('created_at','desc')
            ->paginate($perPage)
            ->withQueryString();
        return Inertia::render('Inventories/Items/FastOpening', [
            'items'        => $items,
        ]);
    }

    public function Store(FastOpeningRequest $request)
    {
        $validated = $request->validated();
        $rows = collect($validated['items']);
        // Only keep items that have quantity > 0
        $itemsWithQuantity = $rows->filter(function ($item) {
            return isset($item['quantity']) && $item['quantity'] > 0;
        });
        DB::transaction(function () use ($itemsWithQuantity) {
            foreach ($itemsWithQuantity as $itemData) {
                // Create new stock + opening
            $dateConversionService = app(abstract: \App\Services\DateConversionService::class);

                $expire_date = $itemData['expire_date'] ? $dateConversionService->toGregorian($itemData['expire_date']) : null;
                $stockService = app(\App\Services\StockService::class);
                    $stock = $stockService->post([
                        'item_id'         => $itemData['item_id'],
                        'movement_type'   => StockMovementType::IN->value,
                        'unit_measure_id' => $itemData['unit_measure_id'], // from item form
                        'quantity'        => (float) $itemData['quantity'],
                        'source'          => StockSourceType::OPENING->value,
                        'unit_cost'       => (float) $itemData['cost'],
                        'status'          => StockStatus::DRAFT->value,
                        'batch'           => $itemData['batch'] ?? null,
                        'date'            => Carbon::now()->toDateString(),
                        'expire_date'     => $expire_date,
                        'size_id'         => $itemData['size_id'] ?? null,
                        'warehouse_id'    => $itemData['warehouse_id'],
                        'branch_id'       => auth()->user()->company->branch_id,
                    ]); 

                    $homeCurrency = Cache::get('home_currency');
                    $date = Carbon::now()->toDateString();
                    $glAccounts = Cache::get('gl_accounts');
                    $transactionService = app(TransactionService::class);
                    $transaction = $transactionService->post(
                        header: [
                            'currency_id' => $homeCurrency->id,
                            'rate' => 1,
                            'date' => $date,
                            'reference_type' => Item::class,
                            'reference_id' => $itemData['item_id'],
                            'remark' => 'Opening balance for item ' . Item::find($itemData['item_id'])->name,
                        ],
                        lines: [
                            [
                                'account_id' => Item::find($itemData['item_id'])->asset_account_id,
                                'debit' => $itemData['cost']*$itemData['quantity'],
                                'credit' => 0,
                            ],
                            [
                                'account_id' => $glAccounts['opening-balance-equity'],
                                'debit' => 0,
                                'credit' => $itemData['cost']*$itemData['quantity'],
                            ]
                        ]
                    );
            }

        });
    }
}

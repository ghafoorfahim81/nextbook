<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\FastOpeningRequest;
use App\Models\Administration\Store;
use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockOpening;
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
                    $stock = Stock::create([
                        'item_id'         => $itemData['item_id'],
                        'quantity'        => $itemData['quantity'],
                        'unit_measure_id' => $itemData['unit_measure_id'],
                        'expire_date'     => $itemData['expire_date'] ?? null,
                        'batch'           => $itemData['batch'] ?? null,
                        'unit_price'      => $itemData['cost'] ?? null,
                        'store_id'        => $itemData['store_id']??Store::where('is_main',true)->first()->id,
                    ]);

                    $stock->opening()->create([
                        'item_id' => $itemData['item_id']
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

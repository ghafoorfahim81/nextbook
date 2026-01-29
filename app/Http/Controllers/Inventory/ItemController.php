<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ItemStoreRequest;
use App\Http\Requests\Inventory\ItemUpdateRequest;
use App\Http\Resources\Inventory\ItemResource;
use App\Models\Inventory\Item;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockOpening;
use App\Models\Inventory\StockOut;
use App\Models\Transaction\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Http\Resources\Inventory\StockResource;
use App\Http\Resources\Inventory\StockOutResource;
use App\Models\Account\Account;
use App\Models\Inventory\ItemOpeningTransaction;
class ItemController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Item::class, 'item');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $items = Item::with('category', 'unitMeasure', 'size')
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Inventories/Items/Index', [
            'items' => ItemResource::collection($items),
        ]);
    }

    public function create()
    {
        // Get the maximum code as integer (cast to handle mixed formats like "3" and "004")
        $maxCode = Item::query()->selectRaw('MAX(CAST(code AS INTEGER)) as max_code')
        ->value('max_code');
        $maxCode = $maxCode ? intval($maxCode) + 1 : 1;

        return inertia('Inventories/Items/Create', [
            'maxCode' => $maxCode,
        ]);
    }
    public function store(ItemStoreRequest $request)
    {

        $validated = $request->validated();
        // If you're uploading a photo here, handle it first (optional)
        // if ($request->hasFile('photo')) {
        //     $path = $request->file('photo')->store('items', 'public');
        //     $validated['photo'] = $path;
        // }

        DB::transaction(function () use ($validated, $request) {
            // 1) Create item
            $item = Item::create($validated);
            // 2) Create opening stocks (if any)
            $openings = collect($validated['openings'] ?? []);
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $transactionService = app(\App\Services\TransactionService::class);
            $date = $dateConversionService->toGregorian(Carbon::now()->toDateString()); 
            $openings
                ->filter(function ($o) {
                    return !empty($o['store_id']) && $o['quantity'] > 0;
                })
                ->each(function ($o) use ($item, $validated, $dateConversionService, $transactionService, $date) { 
                    $cost = (float)($validated['cost'] ?? $validated['purchase_price'] ?? 0);
                    $expire_date = $o['expire_date'] ? $dateConversionService->toGregorian($o['expire_date']) : null;
                    // create stock
                    $stockService = app(\App\Services\StockService::class);
                    $stock = $stockService->addStock([
                        'item_id' => $item->id,
                        'store_id' => $o['store_id'],
                        'unit_measure_id' => $validated['unit_measure_id'], // from item form
                        'quantity'        => (float) $o['quantity'],
                        'unit_price'      => $cost,
                        'free'            => isset($o['free']) ? (float) $o['free'] : null,
                        'batch'           => $o['batch'] ?? null,
                        'date'            => $date,
                        'expire_date'     => $expire_date,
                        'size_id'         => $validated['size_id'] ?? null,
                    ], $o['store_id'], 'opening', $item->id);

                    // mark it as an opening
                    StockOpening::create([
                        'id'      => (string) Str::ulid(),
                        'item_id' => $item->id,
                        'stock_id' => $stock->id,
                    ]); 
                });
                // Create opening transactions
                if ($openings->filter(function ($o) {
                    return !empty($o['store_id']) && $o['quantity'] > 0;
                })->count() > 0) {
                    $glAccounts      = Cache::get('gl_accounts');
                    $homeCurrency = Cache::get('home_currency');
                    $amount = $openings->sum(function ($o) {
                        return (float)($o['quantity'] ?? 0);
                    });
                    $cost = (float)($validated['cost'] ?? $validated['purchase_price'] ?? 0);
                    $inventoryTransaction = $transactionService->createTransaction([
                        'account_id' => $glAccounts['inventory-stock'],
                        'amount' => $cost*$amount,
                        'currency_id' => $homeCurrency->id,
                        'rate' => 1,
                        'date' => $date,
                        'type' => 'debit',
                        'remark' => 'Opening balance for item ' . $item->name,
                        'reference_type' => Item::class,
                        'reference_id' => $item->id,
                    ]);
                    $openingBalanceTransaction = $transactionService->createTransaction([
                        'account_id' => $glAccounts['retained-earnings'],
                        'amount' => $cost*$amount,
                        'currency_id' => $homeCurrency->id,
                        'rate' => 1,
                        'date' => $date,
                        'type' => 'credit',
                        'remark' => 'Opening balance for item ' . $item->name,
                        'reference_type' => Item::class,
                        'reference_id' => $item->id,
                    ]);
                    ItemOpeningTransaction::create([
                        'id' => (string) Str::ulid(),
                        'item_id' => $item->id,
                        'inventory_transaction_id' => $inventoryTransaction->id,
                        'opening_balance_transaction_id' => $openingBalanceTransaction->id,
                        'created_by' => auth()->id(),
                    ]);
                }

        });
        if ((bool) $request->input('stay') || (bool) $request->input('create_and_new')) {
            return redirect()->route('items.create')->with('success', __('general.created_successfully', ['resource' => __('general.resource.item')]));
        }
        return redirect()->route('items.index')->with('success', __('general.items_created_successfully'));
    }

    public function show(Request $request, Item $item)
    {
        // $item->load(['stock_count', 'stock_out_count']);
        return inertia('Inventories/Items/Show', [
            'item' => new ItemResource($item),
        ]);
    }
    public function inRecords(Request $request, Item $item)
    {
        $stocks = Stock::with(['store', 'unitMeasure', 'source'])
            ->where('item_id', $item->id)
            ->orderBy('date', 'desc')
            ->paginate($request->input('per_page', 10));

        return response()->json([
            'data' => StockResource::collection($stocks),
            'meta' => [
                'current_page' => $stocks->currentPage(),
                'last_page' => $stocks->lastPage(),
                'per_page' => $stocks->perPage(),
                'total' => $stocks->total(),
            ],
        ]);
    }

    public function outRecords(Request $request, Item $item)
    {
        $stockOuts = StockOut::with(['store', 'unitMeasure', 'source'])
            ->where('item_id', $item->id)
            ->orderBy('date', 'desc')
            ->paginate($request->input('per_page', 10));

        return response()->json([
            'data' => StockOutResource::collection($stockOuts),
            'meta' => [
                'current_page' => $stockOuts->currentPage(),
                'last_page' => $stockOuts->lastPage(),
                'per_page' => $stockOuts->perPage(),
                'total' => $stockOuts->total(),
            ],
        ]);
    }

    public function edit(Request $request, Item $item)
    {
        $item = Item::with('unitMeasure', 'brand', 'category', 'size')->find($item->id);
        return inertia('Inventories/Items/Edit', [
            'item' => new ItemResource($item)
        ]);
    }

    public function update(ItemUpdateRequest $request, Item $item)
    {
        $validated = $request->validated();
        // Handle photo update
        // dd($request->all());
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('items', 'public');
            $validated['photo'] = $path;
        }
        DB::transaction(function () use ($validated, $item) {
            // 1) Update item
            $item->update($validated);

            // 2) Handle openings
            $openings = collect($validated['openings'] ?? []);
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $date =   $dateConversionService->toGregorian(Carbon::now()->toDateString());
            $transactionService = app(\App\Services\TransactionService::class);
            // Remove old openings (optional: you may also soft-delete instead)
            $item->openings->each(function ($opening) {
                $opening->forceDelete();
                $opening->stock()->forceDelete();
            });
            $openings
                ->filter(fn($o) => !empty($o['store_id']) && (float)($o['quantity'] ?? 0) > 0)
                ->each(function ($o) use ($item, $validated, $dateConversionService, $date) {

                    $cost = (float)($validated['cost'] ?? $validated['purchase_price'] ?? 0);
                    $expire_date = $o['expire_date'] ? $dateConversionService->toGregorian($o['expire_date']) : null;
                    $stockService = app(\App\Services\StockService::class);
                    $stock = $stockService->addStock([
                        'item_id' => $item->id,
                        'store_id' => $o['store_id'],
                        'unit_measure_id' => $validated['unit_measure_id'], // from item form
                        'quantity'        => (float) $o['quantity'],
                        'unit_price'      => $cost,
                        'free'            => isset($o['free']) ? (float) $o['free'] : null,
                        'batch'           => $o['batch'] ?? null,
                        'date'            => $date,
                        'expire_date'     => $expire_date,
                        'size_id'         => $validated['size_id'] ?? null,
                    ], $o['store_id'], 'opening', $item->id);

                    StockOpening::create([
                        'id'      => (string) Str::ulid(),
                        'item_id' => $item->id,
                        'stock_id' => $stock->id,
                    ]);
                });

                // Delete opening stocks
                $filteredOpenings = $openings->filter(fn($o) => !empty($o['store_id']) && (float)($o['quantity'] ?? 0) > 0);
                if ($filteredOpenings->count() == 0 && $item->openings()->count() > 0) {
                    $item->openings()->each(function ($opening) {
                        $opening->forceDelete();
                        $opening->stock->forceDelete();
                    });

                    // Delete opening transactions
                    $item->openingTransactions()->each(function ($openingTransaction) {
                        $inventoryTransactionId = $openingTransaction->inventory_transaction_id;
                        $openingBalanceTransactionId = $openingTransaction->opening_balance_transaction_id;

                        // Delete the opening transaction first to remove foreign key constraints
                        $openingTransaction->forceDelete();

                        // Then safely delete the related transactions
                        if ($inventoryTransactionId) {
                            Transaction::where('id', $inventoryTransactionId)->forceDelete();
                        }
                        if ($openingBalanceTransactionId) {
                            Transaction::where('id', $openingBalanceTransactionId)->forceDelete();
                        }
                    });
                }

                $item->openingTransactions()->each(function ($openingTransaction) {
                    // Store transaction IDs before deleting the opening transaction
                    $inventoryTransactionId = $openingTransaction->inventory_transaction_id;
                    $openingBalanceTransactionId = $openingTransaction->opening_balance_transaction_id;

                    // Delete the opening transaction first to remove foreign key constraints
                    $openingTransaction->forceDelete();

                    // Then safely delete the related transactions
                    if ($inventoryTransactionId) {
                        Transaction::where('id', $inventoryTransactionId)->forceDelete();
                    }
                    if ($openingBalanceTransactionId) {
                        Transaction::where('id', $openingBalanceTransactionId)->forceDelete();
                    }
                });

                // Create opening transactions
                if ($filteredOpenings->filter(fn($o) => !empty($o['store_id']) && (float)($o['quantity'] ?? 0) > 0)->count() > 0) {
                    $glAccounts = Cache::get('gl_accounts');
                    $homeCurrency = Cache::get('home_currency');
                    $amount = $filteredOpenings->sum(function ($o) {
                        return (float)($o['quantity'] ?? 0);
                    });
                    $cost = (float)($validated['cost'] ?? $validated['purchase_price'] ?? 0);
                    $inventoryTransaction = $transactionService->createTransaction([
                        'account_id' => $glAccounts['inventory-stock'],
                        'amount' => $cost*$amount,
                        'currency_id' => $homeCurrency->id,
                        'rate' => 1,
                        'date' => $date,
                        'type' => 'debit',
                        'remark' => 'Opening balance for item ' . $item->name,
                        'reference_type' => Item::class,
                        'reference_id' => $item->id,
                    ]);
                    $openingBalanceTransaction = $transactionService->createTransaction([
                        'account_id' => $glAccounts['retained-earnings'],
                        'amount' => $cost*$amount,
                        'currency_id' => $homeCurrency->id,
                        'rate' => 1,
                        'date' => $date,
                        'type' => 'credit',
                        'remark' => 'Opening balance for item ' . $item->name,
                        'reference_type' => Item::class,
                        'reference_id' => $item->id,
                    ]);
                    ItemOpeningTransaction::create([
                        'id' => (string) Str::ulid(),
                        'item_id' => $item->id,
                        'inventory_transaction_id' => $inventoryTransaction->id,
                        'opening_balance_transaction_id' => $openingBalanceTransaction->id,
                            'created_by' => auth()->id(),
                        ]);
                }

        });

        return redirect()->route('items.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.item')]));
    }


    public function destroy(Request $request, Item $item)
    {
        try {
            // Check for dependencies before deletion
            if (!$item->canBeDeleted()) {
                $message = $item->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
                return inertia('Inventories/Items/Index', [
                    'error' => $message
                ]);
            }

            DB::transaction(function () use ($item) {
                // Delete openings with their stocks (with existence checks)
                $item->openings()->with('stock')->each(function ($opening) {
                    if ($opening->stock) {
                        $opening->stock->delete();
                    }
                    $opening->delete();
                });

                // Delete opening transactions with their related models
                $item->openingTransactions()->with(['inventoryTransaction', 'openingBalanceTransaction'])->each(function ($openingTransaction) {
                    if ($openingTransaction->inventoryTransaction) {
                        $openingTransaction->inventoryTransaction->delete();
                    }
                    if ($openingTransaction->openingBalanceTransaction) {
                        $openingTransaction->openingBalanceTransaction->delete();
                    }
                    $openingTransaction->delete();
                });

                // Finally delete the main item
                $item->delete();
            });

            return redirect()->route('items.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.item')]));

        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error deleting item: ' . $e->getMessage(), [
                'item_id' => $item->id,
                'exception' => $e
            ]);

            return redirect()->back()->with('error', __('general.failed_to_delete_try_again', ['resource' => __('general.resource.item')]));
        }
    }
    public function restore(Request $request, Item $item)
    {
        try {
            DB::transaction(function () use ($item) {
                // Restore the main item first
                $item->restore();

                // Restore openings with their stocks (only trashed records)
                $item->openings()->with(['stock' => function ($query) {
                    $query->withTrashed();
                }])->withTrashed()->each(function ($opening) {
                    if ($opening->stock) {
                        $opening->stock->restore();
                    }
                    $opening->restore();
                });

                // Restore opening transactions with their related models
                $item->openingTransactions()->with([
                    'inventoryTransaction' => function ($query) {
                        $query->withTrashed();
                    },
                    'openingBalanceTransaction' => function ($query) {
                        $query->withTrashed();
                    }
                ])->withTrashed()->each(function ($openingTransaction) {
                    if ($openingTransaction->inventoryTransaction) {
                        $openingTransaction->inventoryTransaction->restore();
                    }
                    if ($openingTransaction->openingBalanceTransaction) {
                        $openingTransaction->openingBalanceTransaction->restore();
                    }
                    $openingTransaction->restore();
                });
            });

            return redirect()->route('items.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.item')]));

        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error restoring item: ' . $e->getMessage(), [
                'item_id' => $item->id,
                'exception' => $e
            ]);

            return redirect()->back()->with('error', __('general.failed_to_restore_try_again', ['resource' => __('general.resource.item')]));
        }
    }

    // force delete item
    public function forceDelete(Request $request, Item $item)
    {
        try {
            DB::transaction(function () use ($item) {
                // Force delete openings with their stocks
                $item->openings()->with('stock')->each(function ($opening) {
                    if ($opening->stock) {
                        $opening->stock->forceDelete();
                    }
                    $opening->forceDelete();
                });

                // Force delete opening transactions with their related models
                $item->openingTransactions()->with(['inventoryTransaction', 'openingBalanceTransaction'])->each(function ($openingTransaction) {
                    if ($openingTransaction->inventoryTransaction) {
                        $openingTransaction->inventoryTransaction->forceDelete();
                    }
                    if ($openingTransaction->openingBalanceTransaction) {
                        $openingTransaction->openingBalanceTransaction->forceDelete();
                    }
                    $openingTransaction->forceDelete();
                });

                // Finally force delete the main item
                $item->forceDelete();
            });

            return redirect()->route('items.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.item')]));

        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error force deleting item: ' . $e->getMessage(), [
                'item_id' => $item->id,
                'exception' => $e
            ]);

            return redirect()->back()->with('error', __('general.failed_to_permanently_delete_try_again', ['resource' => __('general.resource.item')]));
        }
    }
}

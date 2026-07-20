<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ItemStoreRequest;
use App\Http\Requests\Inventory\ItemUpdateRequest;
use App\Http\Resources\Inventory\ItemResource;
use App\Http\Resources\Inventory\ItemListResource;
use App\Models\Inventory\Item;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Models\Account\Account;
use App\Enums\ItemType;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Category;
use App\Models\Administration\Brand;
use App\Models\Administration\Size;
use App\Models\Administration\Warehouse;
use App\Models\User;
use App\Enums\StockSourceType;
use App\Enums\StockMovementType;
use App\Enums\StockStatus;
use App\Http\Resources\Inventory\StockMovementResource;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockBalance;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use App\Services\SpreadsheetExportService;
use App\Services\AttachmentService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
class ItemController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Item::class, 'item');
    }

    public function index(Request $request)
    {
        // Update each item's avg_cost based on all IN-type stock movements, considering multi-unit

        // Get all items with at least one stock movement (IN type)
        // Item::chunk(500, function ($items) {
        //     foreach ($items as $item) {
        //         // Get all IN-type stock movements for this item
        //         $movements = StockMovement::where('item_id', $item->id)
        //             ->where('movement_type', StockMovementType::IN->value)
        //             ->get();

        //         $totalBaseQty = 0.0;
        //         $totalCost = 0.0;
        //         foreach ($movements as $movement) {
        //             // Get item's base unit (unit_measure_id)
        //             $itemUnitId = $item->unit_measure_id;
        //             $movementUnitId = $movement->unit_measure_id;

        //             // Calculate conversion factor between movement's unit and item's base unit
        //             if ($itemUnitId == $movementUnitId || !$movementUnitId) {
        //                 $factor = 1;
        //             } else {
        //                 // fetch UnitMeasure for conversion
        //                 $itemUnit = \App\Models\Administration\UnitMeasure::find($itemUnitId);
        //                 $movementUnit = \App\Models\Administration\UnitMeasure::find($movementUnitId);

        //                 $itemBase = $itemUnit?->unit ?: 1;
        //                 $movementBase = $movementUnit?->unit ?: 1;

        //                 // Avoid division by zero
        //                 $factor = ($itemBase != 0) ? ($movementBase / $itemBase) : 1;
        //             }

        //             // Convert movement quantity to item's base unit
        //             $qtyInBaseUnit = $movement->quantity * $factor;
        //             $lineCost = $movement->unit_cost * $movement->quantity;

        //             $totalBaseQty += $qtyInBaseUnit;
        //             $totalCost += $lineCost;
        //         }

        //         $avgCost = $totalBaseQty > 0 ? $totalCost / $totalBaseQty : 0;

        //         // Only update if there's a difference, to avoid unnecessary writes
        //         if ((float) $item->avg_cost !== (float) $avgCost) {
        //             $item->avg_cost = $avgCost;
        //             $item->save();
        //         }
        //     }
        // });

            // dd($mappedMovements->toArray());

        //     $avgCosts = StockMovement::query()
        //     ->selectRaw('item_id, SUM(quantity) as total_quantity, SUM(unit_cost * quantity) as total_cost')
        //     ->where('movement_type', StockMovementType::IN->value)
        //     ->groupBy('item_id')
        //     ->get()
        //     ->mapWithKeys(function ($itemMovement) {
        //         // Avoid division by zero just in case
        //         $avgCost = ($itemMovement->total_quantity != 0)
        //             ? $itemMovement->total_cost / $itemMovement->total_quantity
        //             : 0;
        //         return [$itemMovement->item_id => $avgCost];
        //     })
        //     ->toArray();

        // // Handle in batches to avoid memory/timeout issues on large data sets
        // foreach (array_chunk($avgCosts, 1000, true) as $batch) {
        //     // Build a single CASE WHEN SQL expression for batch update
        //     $ids = array_keys($batch);
        //     $caseSql = "CASE id ";
        //     foreach ($batch as $itemId => $avgCost) {
        //         $caseSql .= "WHEN '" . addslashes($itemId) . "' THEN " . (float)$avgCost . " ";
        //     }
        //     $caseSql .= "END";
        //     // Run a single update query for this batch
        //     Item::whereIn('id', $ids)->update([
        //         'avg_cost' => DB::raw($caseSql),
        //     ]);
        // }

        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $items = Item::search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();


        return inertia('Inventories/Items/Index', [
            'items' => ItemListResource::collection($items),
            'filterOptions' => [
                'itemTypes' => collect(ItemType::cases())->map(fn ($c) => [
                    'id' => $c->value,
                    'name' => $c->getLabel(),
                ])->values(),
                'unitMeasures' => UnitMeasure::orderBy('name')->get(['id', 'name']),
                'categories' => Category::orderBy('name')->get(['id', 'name']),
                'sizes' => Size::orderBy('name')->get(['id', 'name']),
                'brands' => Brand::orderBy('name')->get(['id', 'name']),
                'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
                'users' => User::query()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $sortField,
                'sortDirection' => $sortDirection,
                'filters' => $filters,
            ],
        ]);
    }

    public function create()
    {
        // Get the maximum code as integer (cast to handle mixed formats like "3" and "004")
        $maxCode = Item::query()->selectRaw('MAX(CAST(code AS INTEGER)) as max_code')->whereNull('deleted_at')
        ->value('max_code');
        $maxCode = $maxCode ? intval($maxCode) + 1 : 1;
        $accountModel = new Account();
        $otherCurrentAssetsAccounts = $accountModel->getAccountsByAccountTypeSlug('other-current-asset');
        $incomeAccounts = $accountModel->getAccountsByAccountTypeSlug('income');
        $costAccounts = $accountModel->getAccountsByAccountTypeSlug('cost-of-goods-sold');

        return inertia('Inventories/Items/Create', [
            'maxCode' => $maxCode,
            'otherCurrentAssetsAccounts' => $otherCurrentAssetsAccounts,
            'incomeAccounts' => $incomeAccounts,
            'costAccounts' => $costAccounts,
        ]);
    }
    public function store(ItemStoreRequest $request, AttachmentService $attachmentService)
    {
        $validated = $request->validated();
        $validated['item_type'] = $validated['item_type']??ItemType::INVENTORY_MATERIALS->value;
        DB::transaction(function () use ($validated, $request, $attachmentService) {
            // 1) Create item
            $item = Item::create($validated);

            if ($request->hasFile('attachments')) {
                $attachmentService->store($item, $request->file('attachments'));
            }
            // 2) Create opening stocks (if any)
            $openings = collect($validated['openings'] ?? []);
            $transactionService = app(\App\Services\TransactionService::class);
            $openings
                ->filter(function ($o) {
                    return !empty($o['warehouse_id']) && $o['quantity'] > 0;
                })
                ->each(function ($o) use ($item, $validated, $transactionService) {
                    // create stock
                    $stockService = app(\App\Services\StockService::class);
                    $branchId = auth()->user()->branch_id ?? app('active_branch_id');
                    $stock = $stockService->post([
                        'item_id'         => $item->id,
                        'movement_type'   => StockMovementType::IN->value,
                        'unit_measure_id' => $validated['unit_measure_id'], // from item form
                        'quantity'        => (float) $o['quantity'],
                        'source'          => StockSourceType::OPENING->value,
                        'unit_cost'       => (float) $o['unit_price'],
                        'status'          => StockStatus::DRAFT->value,
                        'batch'           => $o['batch'] ?? null,
                        'date'            => Carbon::now()->toDateString(),
                        'expire_date'     => $o['expire_date'] ?? null,
                        'size_id'         => $o['size_id'] ?? $validated['size_id'] ?? null,
                        'color'           => $o['color'] ?? null,
                        'warehouse_id'    => $o['warehouse_id'],
                        'branch_id'       => $branchId,
                    ]);
                });
                // Create opening transactions
                if ($openings->filter(function ($o) {
                    return !empty($o['warehouse_id']) && $o['quantity'] > 0;
                })->count() > 0) {
                    $glAccounts      = Cache::get('gl_accounts');
                    $homeCurrency = Cache::get('home_currency');
                    $openingBalanceAccount = $glAccounts['opening-balance-equity'];
                    $itemType = $validated['item_type'];
                    if ($itemType == ItemType::INVENTORY_MATERIALS->value) {
                        $inventoryAccount = $validated['asset_account_id'] ?? $glAccounts['inventory-stock'];
                    }
                    elseif ($itemType == ItemType::NON_INVENTORY_MATERIALS->value) {
                        $inventoryAccount = $validated['asset_account_id'] ?? $glAccounts['non-inventory-items'];
                    }
                    elseif ($itemType == ItemType::RAW_MATERIALS->value) {
                        $inventoryAccount = $validated['asset_account_id'] ?? $glAccounts['raw-materials'];
                    }
                    elseif ($itemType == ItemType::FINISHED_GOOD_ITEMS->value) {
                        $inventoryAccount = $validated['asset_account_id'] ?? $glAccounts['finished-goods'];
                    }
                    $openingBalanceTransaction = $transactionService->post(
                        header: [
                          'currency_id' => $homeCurrency->id,
                          'rate' => 1,
                          'voucher_number' => 'Opening Balance ' .$item->name. ' #' . $item->code,
                          'date' => Carbon::now(),
                          'reference_type' => Item::class,
                          'reference_id' => $item->id,
                          'remark' => 'Opening balance for item ' . $item->name,
                        ],
                        lines: [
                          ['account_id' => $inventoryAccount,   'debit' => $openings->sum(function ($o) {
                            return round((float)($o['quantity'] ?? 0), 4) * (float)($o['unit_price'] ?? 0);
                        }), 'credit' => 0,
                        'remark' => 'Opening balance for item ' . ' ' . $item->name,
                        'remark_fa' => 'موجودی اولیه برای جنس ' . ' ' . $item->name,
                         'remark_ps' =>'د'. ' '. $item->name.' '.'د پرانیستلو بیلانس ',
                        ],
                          ['account_id' => $openingBalanceAccount, 'debit' => 0,'credit' => $openings->sum(function ($o) {
                            return round((float)($o['quantity'] ?? 0), 4) * (float)($o['unit_price'] ?? 0);
                        }), 'remark' => 'Opening balance for item ' . ' ' . $item->name,
                        'remark_fa' => 'موجودی اولیه برای جنس ' . ' ' . $item->name,
                        'remark_ps' =>'د'. ' '. $item->name.' '.'د پرانیستلو بیلانس ',
                        ],
                        ]
                      );
                }
        });
        if ((bool) $request->input('stay') || (bool) $request->input('create_and_new')) {
            return redirect()->route('items.create')->with('success', __('general.created_successfully', ['resource' => __('general.resource.item')]));
        }
        return redirect()->route('items.index')->with('success', __('general.items_created_successfully'));
    }

    public function show(Request $request, Item $item)
    {
        $item->load('assetAccount', 'incomeAccount', 'costAccount', 'createdBy', 'updatedBy', 'brand', 'size', 'stocks', 'attachments');

        if ($request->expectsJson()) {
            return response()->json([
                'data' => ItemResource::make($item),
            ]);
        }

        return inertia('Inventories/Items/Show', [
            'item' => ItemResource::make($item),
        ]);
    }
    public function inRecords(Request $request, Item $item)
    {
        $stocks = $this->stockMovementQuery($item, StockMovementType::IN->value)
            ->paginate($request->input('per_page', 10));

        return response()->json([
            'data' => StockMovementResource::collection($stocks),
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
        $stockOuts = $this->stockMovementQuery($item, StockMovementType::OUT->value)
            // ->paginate($request->input('per_page', 100));
            ->paginate( 1000);

        return response()->json([
            'data' => StockMovementResource::collection($stockOuts),
            'meta' => [
                'current_page' => $stockOuts->currentPage(),
                'last_page' => $stockOuts->lastPage(),
                'per_page' => $stockOuts->perPage(),
                'total' => $stockOuts->total(),
            ],
        ]);
    }

    public function exportInRecords(Request $request, Item $item, SpreadsheetExportService $spreadsheetExportService)
    {
        return $this->exportStockMovements(
            request: $request,
            item: $item,
            movementType: StockMovementType::IN->value,
            sheetLabel: $spreadsheetExportService->localeTranslation('item', 'in_records', 'In Records'),
            filenamePrefix: 'item-in-records',
            spreadsheetExportService: $spreadsheetExportService,
        );
    }

    public function exportOutRecords(Request $request, Item $item, SpreadsheetExportService $spreadsheetExportService)
    {
        return $this->exportStockMovements(
            request: $request,
            item: $item,
            movementType: StockMovementType::OUT->value,
            sheetLabel: $spreadsheetExportService->localeTranslation('item', 'out_records', 'Out Records'),
            filenamePrefix: 'item-out-records',
            spreadsheetExportService: $spreadsheetExportService,
        );
    }

    public function export(Request $request, SpreadsheetExportService $spreadsheetExportService)
    {
        $this->authorize('viewAny', Item::class);

        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $items = Item::with(['unitMeasure'])
            ->withSum(['stocks as total_in' => fn ($q) => $q->where('movement_type', StockMovementType::IN->value)], 'quantity')
            ->withSum(['stocks as total_out' => fn ($q) => $q->where('movement_type', StockMovementType::OUT->value)], 'quantity')
            ->withSum('stockBalances as on_hand', 'quantity')
            ->withSum([
                'stocks as opening_balance' => function ($q) {
                    $q->where('source', \App\Enums\StockSourceType::OPENING->value);
                }
            ], 'quantity')
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->get();
       

        $rows = $items->map(fn ($item) => [
            'name' => $item->name ?? '-',
            'code' => $item->code ?? '-',
            'unit_measure' => $item->unitMeasure?->name ?? '-',
            'opening_balance' => (float) ($item->opening_balance ?? 0),
            'avg_cost' => (float) ($item->avg_cost ?? 0),
            'on_hand' => (float) ($item->on_hand ?? 0),
            'purchase_price' => (float) ($item->purchase_price ?? 0),
            'sale_price' => (float) ($item->sale_price ?? 0),
            'total_in' => (float) ($item->total_in ?? 0),
            'total_out' => (float) ($item->total_out ?? 0),
        ])->all();

        $label = $spreadsheetExportService->localeTranslation('item', 'items', 'Items');

        return $spreadsheetExportService->download([
            'filename' => 'items-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name' => $label,
            'sheet_title' => $label,
            'title' => $label,
            'company_name' => $this->exportCompanyName($request),
            'exported_on' => now()->format('Y m d'),
            'rtl' => in_array(app()->getLocale(), ['fa', 'ps'], true),
            'include_row_number' => true,
            'row_number_label' => $spreadsheetExportService->localeTranslation('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'name', 'label' => $spreadsheetExportService->localeTranslation('general', 'name', 'Name'), 'width' => 22],
                ['key' => 'code', 'label' => $spreadsheetExportService->localeTranslation('admin', 'currency.code', 'Code'), 'width' => 10],
                ['key' => 'unit_measure', 'label' => $spreadsheetExportService->localeTranslation('admin', 'unit_measure.unit_measure', 'Unit Measure'), 'width' => 14],
                ['key' => 'opening_balance', 'label' => $spreadsheetExportService->localeTranslation('item', 'opening_balance', 'Opening Balance'), 'type' => 'quantity', 'align' => 'right', 'width' => 12],
                ['key' => 'avg_cost', 'label' => $spreadsheetExportService->localeTranslation('item', 'avg_cost', 'Avg Cost'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'on_hand', 'label' => $spreadsheetExportService->localeTranslation('general', 'on_hand', 'On Hand'), 'type' => 'quantity', 'align' => 'right', 'width' => 12],
                ['key' => 'purchase_price', 'label' => $spreadsheetExportService->localeTranslation('item', 'purchase_price', 'Purchase Price'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'sale_price', 'label' => $spreadsheetExportService->localeTranslation('item', 'sale_price', 'Sale Price'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'total_in', 'label' => $spreadsheetExportService->localeTranslation('item', 'total_in', 'Total In'), 'type' => 'quantity', 'align' => 'right', 'width' => 12],
                ['key' => 'total_out', 'label' => $spreadsheetExportService->localeTranslation('item', 'total_out', 'Total Out'), 'type' => 'quantity', 'align' => 'right', 'width' => 12],
            ],
            'rows' => $rows,
        ]);
    }

    public function edit(Request $request, Item $item)
    {
        $item = Item::with('unitMeasure', 'brand', 'category', 'size', 'assetAccount', 'incomeAccount', 'costAccount', 'openings.warehouse', 'openings.size', 'attachments')->find($item->id);
        $accountModel = new Account();
        $otherCurrentAssetsAccounts = $accountModel->getAccountsByAccountTypeSlug('other-current-asset');
        $incomeAccounts = $accountModel->getAccountsByAccountTypeSlug('income');
        $costAccounts = $accountModel->getAccountsByAccountTypeSlug('cost-of-goods-sold');
        return inertia('Inventories/Items/Edit', [
            'item' => new ItemResource($item),
            'otherCurrentAssetsAccounts' => $otherCurrentAssetsAccounts,
            'incomeAccounts' => $incomeAccounts,
            'costAccounts' => $costAccounts,
        ]);
    }

    public function update(ItemUpdateRequest $request, Item $item, AttachmentService $attachmentService)
    {
        $validated = $request->validated();
        // Handle photo update
        // dd($request->all());
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('items', 'public');
            $validated['photo'] = $path;
        }
        DB::transaction(function () use ($validated, $item, $request, $attachmentService) {
            if ($request->hasFile('attachments')) {
                $attachmentService->store($item, $request->file('attachments'));
            }
            // $existingDraftOpenings = $item->openings()
            //     ->where('status', StockStatus::DRAFT->value)
            //     ->orderBy('created_at')
            //     ->orderBy('id')
            //     ->get()
            //     ->keyBy('id');

            // 1) Update item
            $item->update($validated);

            // 2) Handle openings
            // $openings = collect($validated['openings'] ?? []);
            // $transactionService = app(\App\Services\TransactionService::class);
            // $submittedOpeningIds = $openings
            //     ->pluck('id')
            //     ->filter()
            //     ->values();

            // $existingDraftOpenings
            //     ->reject(fn (StockMovement $opening) => $submittedOpeningIds->contains($opening->id))
            //     ->each(function (StockMovement $opening) {
            //         $this->deleteOpeningBalance($opening);
            //     });

            // $existingDraftOpenings->each(function (StockMovement $opening) {
            //     $opening->forceDelete();
            // });

            // $openings
            //     ->filter(fn($o) => !empty($o['warehouse_id']) && (float)($o['quantity'] ?? 0) > 0 && $o['status'] == StockStatus::DRAFT->value)
            //     ->each(function ($o) use ($item, $validated, $existingDraftOpenings) {
            //         $existingOpening = !empty($o['id']) ? $existingDraftOpenings->get($o['id']) : null;
            //         $balanceId = $existingOpening ? $this->resolveOpeningBalanceId($existingOpening) : null;
            //         $stockService = app(\App\Services\StockService::class);
            //         $branchId = auth()->user()->branch_id ?? app('active_branch_id');

            //         $stock = $stockService->post([
            //             'item_id'         => $item->id,
            //             'movement_type'   => StockMovementType::IN->value,
            //             'unit_measure_id' => $validated['unit_measure_id'], // from item form
            //             'quantity'        => (float) $o['quantity'],
            //             'source'          => StockSourceType::OPENING->value,
            //             'unit_cost'       => (float) $o['unit_price'],
            //             'status'          => StockStatus::DRAFT->value,
            //             'batch'           => $o['batch'] ?? null,
            //             'date'            => Carbon::now()->toDateString(),
            //             'expire_date'     => $o['expire_date'] ?? null,
            //             'size_id'         => $validated['size_id'] ?? null,
            //             'warehouse_id'    => $o['warehouse_id'],
            //             'branch_id'       => $branchId,
            //             'balance_id'      => $balanceId,
            //             'replace_balance' => $balanceId !== null,
            //         ]);

            //     });

                // Delete opening stocks
                // $filteredOpenings = $openings->filter(fn($o) => !empty($o['warehouse_id']) && (float)($o['quantity'] ?? 0) > 0);

                // $openingTransaction = $item->openingTransaction()->first();
                //     if ($openingTransaction) {
                //         // Then safely delete the related transactions
                //         if ($openingTransaction->id) {
                //             TransactionLine::where('transaction_id', $openingTransaction->id)->forceDelete();
                //             Transaction::where('id', $openingTransaction->id)->forceDelete();
                //         }
                //     }

                // Create opening transactions
                // if ($filteredOpenings->filter(fn($o) => !empty($o['warehouse_id']) && (float)($o['quantity'] ?? 0) > 0)->count() > 0) {
                //     $glAccounts = Cache::get('gl_accounts');
                //     $homeCurrency = Cache::get('home_currency');
                //     $itemType = $validated['item_type'];
                //     $openingBalanceAccount = $glAccounts['opening-balance-equity'];
                //     if ($itemType == ItemType::INVENTORY_MATERIALS->value) {
                //         $inventoryAccount = $glAccounts['inventory-stock'];
                //     }
                //     elseif ($itemType == ItemType::NON_INVENTORY_MATERIALS->value) {
                //         $inventoryAccount = $glAccounts['non-inventory-items'];
                //     }
                //     elseif ($itemType == ItemType::RAW_MATERIALS->value) {
                //         $inventoryAccount = $glAccounts['raw-materials'];
                //     }
                //     elseif ($itemType == ItemType::FINISHED_GOOD_ITEMS->value) {
                //         $inventoryAccount = $glAccounts['finished-goods'];
                //     }
                //     $transaction = $transactionService->post(
                //         header: [
                //           'currency_id' => $homeCurrency->id,
                //           'rate' => 1,
                //           'date' => Carbon::now()->toDateString(),
                //           'reference_type' => Item::class,
                //           'reference_id' => $item->id,
                //           'remark' => 'Opening balance for item ' . $item->name,
                //         ],
                //         lines: [
                //           ['account_id' => $inventoryAccount,   'debit' => $filteredOpenings->sum(function ($o) {
                //             return (float)($o['quantity'] ?? 0) * (float)($o['unit_price'] ?? 0);
                //         }), 'credit' => 0],
                //           ['account_id' => $openingBalanceAccount, 'debit' => 0,    'credit' => $filteredOpenings->sum(function ($o) {
                //             return (float)($o['quantity'] ?? 0) * (float)($o['unit_price'] ?? 0);
                //         })],
                //         ]
                //       );
                // }

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

                $stockMovement = StockMovement::where('item_id', $item->id)
                ->where('status', StockStatus::POSTED->value)
                ->get();
                if($stockMovement->count() > 0) {
                    return redirect()->back()->with([
                        'error' => __('general.cannot_delete_item_with_posted_stock_movements'),
                        'title' => __('general.posted_stock_movements_found'),
                    ]);
                }
                else{
                    $stockMovement = StockMovement::where('item_id', $item->id)
                    ->where('status', StockStatus::DRAFT->value)
                    ->get();
                    if($stockMovement->count() > 0) {
                        $stockMovement->each(function ($movement) {
                            $movement->delete();
                        });
                    }
                    $stockBalance = StockBalance::where('item_id', $item->id)
                    ->where('status', StockStatus::DRAFT->value)
                    ->get();
                    if($stockBalance->count() > 0) {
                        $stockBalance->each(function ($balance) {
                            $balance->delete();
                        });
                    }
                }

                // Delete opening transactions with their related models
                $openingTransaction = $item->openingTransaction()->first();
                    if ($openingTransaction) {
                        // Then safely delete the related transactions
                        if ($openingTransaction->id) {
                            TransactionLine::where('transaction_id', $openingTransaction->id)->delete();
                            Transaction::where('id', $openingTransaction->id)->delete();
                        }
                    }

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
                $item->stocks()->withTrashed()->restore();
                $item->stockBalances()->withTrashed()->restore();

                // Restore opening transactions with their related models
                $openingTransaction = $item->openingTransaction()->withTrashed()->first();
                    if ($openingTransaction) {
                        // Then safely delete the related transactions
                        if ($openingTransaction->id) {
                            $openingTransaction->lines()->withTrashed()->restore();
                            $openingTransaction->restore();
                        }
                    }
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
                $openingTransaction = $item->openingTransaction()->first();
                    if ($openingTransaction) {
                        // Then safely delete the related transactions
                        if ($openingTransaction->id) {
                            TransactionLine::where('transaction_id', $openingTransaction->id)->forceDelete();
                            Transaction::where('id', $openingTransaction->id)->forceDelete();
                        }

                // Finally force delete the main item
                $item->forceDelete();
                }
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

    private function resolveOpeningBalanceId(StockMovement $opening): ?string
    {
        return StockBalance::query()
            ->where('item_id', $opening->item_id)
            ->where('branch_id', $opening->branch_id)
            ->where('warehouse_id', $opening->warehouse_id)
            ->when($opening->batch !== null, function ($query) use ($opening) {
                return $query->where('batch', $opening->batch);
            }, function ($query) {
                return $query->whereNull('batch');
            })
            ->when($opening->expire_date !== null, function ($query) use ($opening) {
                return $query->whereDate('expire_date', $opening->expire_date->toDateString());
            }, function ($query) {
                return $query->whereNull('expire_date');
            })
            ->lockForUpdate()
            ->value('id');
    }

    private function deleteOpeningBalance(StockMovement $opening): void
    {
        $balance = StockBalance::query()
            ->where('item_id', $opening->item_id)
            ->where('branch_id', $opening->branch_id)
            ->where('warehouse_id', $opening->warehouse_id)
            ->when($opening->batch !== null, function ($query) use ($opening) {
                return $query->where('batch', $opening->batch);
            }, function ($query) {
                return $query->whereNull('batch');
            })
            ->when($opening->expire_date !== null, function ($query) use ($opening) {
                return $query->whereDate('expire_date', $opening->expire_date->toDateString());
            }, function ($query) {
                return $query->whereNull('expire_date');
            })
            ->lockForUpdate()
            ->first();

        if ($balance) {
            $balance->forceDelete();
        }
    }

    protected function exportStockMovements(
        Request $request,
        Item $item,
        string $movementType,
        string $sheetLabel,
        string $filenamePrefix,
        SpreadsheetExportService $spreadsheetExportService,
    ) {
        $movements = $this->stockMovementQuery($item, $movementType)->get();
        $rows = collect(StockMovementResource::collection($movements)->resolve())
            ->map(function (array $row) {
                return [
                    'ledger_name' => $row['ledger_name'] ?? '-',
                    'bill_number' => $row['bill_number'] ?? '-',
                    'quantity' => $row['quantity'] ?? 0,
                    'source' => $row['source'] ?? '-',
                    'unit_measure_name' => $row['unit_measure_name'] ?? '-',
                    'date' => $row['date'] ?? '-',
                    'batch' => $row['batch'] ?? '-',
                    'expire_date' => $row['expire_date'] ?? '-',
                    'unit_cost' => $row['unit_cost'] ?? 0,
                    'warehouse_name' => $row['warehouse_name'] ?? '-',
                ];
            })
            ->all();

        $sheetTitle = $item->name . ' - ' . $sheetLabel;

        return $spreadsheetExportService->download([
            'filename' => Str::slug($filenamePrefix . '-' . $item->name) . '-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name' => Str::limit($sheetTitle, 31, ''),
            'sheet_title' => $sheetTitle,
            'title' => $sheetTitle,
            'company_name' => $this->exportCompanyName($request),
            'exported_on' => now()->format('Y m d'),
            'rtl' => in_array(app()->getLocale(), ['fa', 'ps'], true),
            'include_row_number' => true,
            'row_number_label' => $spreadsheetExportService->localeTranslation('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'ledger_name', 'label' => $spreadsheetExportService->localeTranslation('general', 'ledger', 'Ledger'), 'width' => 18],
                ['key' => 'bill_number', 'label' => $spreadsheetExportService->localeTranslation('general', 'bill_number', 'Bill Number'), 'width' => 14],
                ['key' => 'quantity', 'label' => $spreadsheetExportService->localeTranslation('general', 'quantity', 'Quantity'), 'type' => 'quantity', 'align' => 'right', 'width' => 12],
                ['key' => 'source', 'label' => $spreadsheetExportService->localeTranslation('general', 'source', 'Source'), 'width' => 12],
                ['key' => 'unit_measure_name', 'label' => $spreadsheetExportService->localeTranslation('admin', 'unit_measure.unit_measure', 'Unit Measure'), 'width' => 14],
                ['key' => 'date', 'label' => $spreadsheetExportService->localeTranslation('general', 'date', 'Date'), 'width' => 14],
                ['key' => 'batch', 'label' => $spreadsheetExportService->localeTranslation('item', 'batch', 'Batch'), 'width' => 12],
                ['key' => 'expire_date', 'label' => $spreadsheetExportService->localeTranslation('item', 'expire_date', 'Expire Date'), 'width' => 16],
                ['key' => 'unit_cost', 'label' => $spreadsheetExportService->localeTranslation('general', 'unit_price', 'Unit Price'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'warehouse_name', 'label' => $spreadsheetExportService->localeTranslation('admin', 'warehouse.warehouse', 'Warehouse'), 'width' => 18],
            ],
            'rows' => $rows,
        ]);
    }

    protected function stockMovementQuery(Item $item, string $movementType)
    {
        return StockMovement::with([
                'warehouse',
                'unitMeasure',
                'reference' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        Purchase::class => ['supplier'],
                        Sale::class => ['customer'],
                    ]);
                },
            ])
            ->where('item_id', $item->id)
            ->where('movement_type', $movementType)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');
    }

    protected function exportCompanyName(Request $request): string
    {
        $company = data_get($request->user(), 'company');

        if (! $company) {
            return config('app.name');
        }

        return match (app()->getLocale()) {
            'fa' => $company->name_fa ?: $company->name_en ?: $company->abbreviation ?: config('app.name'),
            'ps' => $company->name_pa ?: $company->name_en ?: $company->abbreviation ?: config('app.name'),
            default => $company->name_en ?: $company->abbreviation ?: $company->name_fa ?: $company->name_pa ?: config('app.name'),
        };
    }
}

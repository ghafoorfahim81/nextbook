<?php

namespace App\Http\Controllers;

use App\Http\Resources\Inventory\ItemResource;
use App\Http\Resources\Ledger\LedgerOptionResource;
use App\Models\Administration\UnitMeasure;
use App\Models\Account\Account;
use App\Models\Administration\Size;
use App\Models\Administration\Currency;
use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Administration\Brand;
use App\Models\Administration\Category;
use App\Models\Administration\Warehouse;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseCategory;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Ledger\Ledger;
use App\Models\Owner\Owner;
use App\Models\Payment\Payment;
use App\Models\Purchase\Purchase;
use App\Models\Receipt\Receipt;
use App\Models\Sale\Sale;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    /**
     * Search resources by type
     */
    public function search(Request $request, string $resourceType): JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|min:2|max:255',
            'fields' => 'array',
            'limit' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $searchTerm = $request->input('search', '');
        $fields = $request->input('fields', ['name']);
        $limit = $request->input('limit', 20);
        $additionalParams = $request->except(['search', 'fields', 'limit']);

        if (!$searchTerm) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'resource_type' => $resourceType,
                    'search_term' => $searchTerm,
                    'total' => 0,
                    'limit' => $limit
                ]
            ]);
        }

        try {
            $results = $this->performSearch($resourceType, $searchTerm, $fields, $limit, $additionalParams);

            if ($results instanceof \Illuminate\Http\Resources\Json\AnonymousResourceCollection) {
                return $results->additional([
                    'success' => true,
                    'meta' => [
                        'resource_type' => $resourceType,
                        'search_term' => $searchTerm,
                        'total' => $results->count(),
                        'limit' => $limit
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $results,
                'meta' => [
                    'resource_type' => $resourceType,
                    'search_term' => $searchTerm,
                    'total' => count($results),
                    'limit' => $limit
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perform the actual search based on resource type
     */
    private function performSearch(string $resourceType, string $searchTerm, array $fields, int $limit, array $additionalParams): array|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $searchTerm = '%' . strtolower($searchTerm) . '%';

        switch ($resourceType) {
            case 'ledgers':
                return $this->searchLedgers($searchTerm, $fields, $limit, $additionalParams);

            case 'items':
                return $this->searchItems($searchTerm, $fields, $limit, $additionalParams);

            case 'currencies':
                return $this->searchCurrencies($searchTerm, $fields, $limit, $additionalParams);

            case 'users':
                return $this->searchUsers($searchTerm, $fields, $limit, $additionalParams);

            case 'branches':
                return $this->searchBranches($searchTerm, $fields, $limit, $additionalParams);

            case 'companies':
                return $this->searchCompanies($searchTerm, $fields, $limit, $additionalParams);

            case 'unit_measures':
                return $this->searchUnitMeasures($searchTerm, $fields, $limit, $additionalParams);

            case 'brands':
                return $this->searchBrands($searchTerm, $fields, $limit, $additionalParams);

            case 'categories':
                return $this->searchCategories($searchTerm, $fields, $limit, $additionalParams);

            case 'warehouses':
                return $this->searchWarehouses($searchTerm, $fields, $limit, $additionalParams);

            case 'expense_categories':
                return $this->searchExpenseCategories($searchTerm, $fields, $limit, $additionalParams);

            case 'accounts':
                return $this->searchAccounts($searchTerm, $fields, $limit, $additionalParams);
            case 'sizes':
                return $this->searchSizes($searchTerm, $fields, $limit, $additionalParams);
            default:
                throw new \InvalidArgumentException("Unsupported resource type: {$resourceType}");
        }
    }

    /**
     * Search ledgers (suppliers, customers, etc.)
     */
    private function searchLedgers(string $searchTerm, array $fields, int $limit, array $additionalParams): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $query = Ledger::query()
            ->select([
                'id',
                'name',
                'code',
                'type',
                'email',
                'phone_no',
                'address',
                'currency_id',
                'is_active',
                'branch_id',
            ])
            ->withStatementTotals() 
            ->where('is_active', true)
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'code', 'email', 'phone_no', 'address'], true)) {
                        $q->orWhere($field, 'ilike', $searchTerm);
                    }
                }
            });

        // Add additional filters if provided
        if (isset($additionalParams['type'])) {
            $query->where('type', $additionalParams['type']);
        }

        if (isset($additionalParams['branch_id'])) {
            $query->where('branch_id', $additionalParams['branch_id']);
        }

        return LedgerOptionResource::collection(
            $query->orderBy('created_at','desc')->limit($limit)->get()
        );
    }

    /**
     * Search items
     */
    private function searchItems(string $searchTerm, array $fields, int $limit, array $additionalParams): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        // Use Eloquent so ItemResource receives models (not stdClass) and can access relations
        $query = Item::query()
            ->with(['unitMeasure', 'brand', 'category', 'stocks', 'openings', 'stockOut'])
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'code', 'generic_name', 'packing', 'barcode', 'fast_search'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') iLike ?', [$searchTerm]);
                    }
                }
            });

        // Add additional filters if provided
        if (isset($additionalParams['category_id'])) {
            $query->where('category_id', $additionalParams['category_id']);
        }

        if (isset($additionalParams['brand_id'])) {
            $query->where('brand_id', $additionalParams['brand_id']);
        }

        if (isset($additionalParams['branch_id'])) {
            $query->where('branch_id', $additionalParams['branch_id']);
        }

        // For sales, filter items that have available stock in the specified warehouse
        if (isset($additionalParams['warehouse_id'])) {
            $warehouseId = $additionalParams['warehouse_id'];
            $query->whereHas('stocks', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)
                  ->where('quantity', '>', 0);
            });
        }

        return ItemResource::collection($query->limit($limit)->get());
    }

    /**
     * Search items for sales with store filtering and batch information
     */
    public function searchItemsList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'warehouse_id' => 'nullable|string|exists:warehouses,id',
            'search' => 'nullable|string|max:255',
            'search_query' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:250',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $searchTerm = trim($request->input('search_query') ?? $request->input('search', ''));
        $limit = $this->resolveItemLimit($request->input('limit'), $searchTerm);
        $inStockOnly = $request->boolean('in_stock_only', true);
        $requestedWarehouseId = trim((string) ($request->input('warehouse_id') ?? ''));
        $warehouseId = $requestedWarehouseId ?: Warehouse::main()?->id;
        if (!$warehouseId || !Warehouse::query()->where('id', $warehouseId)->first()) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found',
            ], 404);
        }

        try {
            $items = $this->gatherItemsFromWarehouse($warehouseId, $searchTerm, $limit, $inStockOnly);

            return response()->json([
                'success' => true,
                'data' => $items,
                'meta' => [
                    'warehouse_id' => $warehouseId,
                    'search_query' => $searchTerm,
                    'limit' => $limit,
                    'total' => count($items),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function gatherItemsFromWarehouse(string $warehouseId, string $searchTerm, int $limit, bool $inStockOnly = true): array
    {
        $searchableFields = ['name', 'code', 'generic_name', 'packing', 'barcode', 'fast_search'];

        $query = Item::query()
            ->select([
                'id', 'name', 'code', 'generic_name', 'packing', 'barcode',
                'unit_measure_id', 'brand_id', 'category_id', 'colors', 'size_id',
                'purchase_price', 'sale_price', 'margin_percentage', 'rate_a', 'rate_b', 'rate_c', 'rack_no', 'fast_search',
                'avg_cost',
            ])
            ->with(['unitMeasure', 'brand', 'category', 'size'])
            ->when($searchTerm, function ($query) use ($searchTerm, $searchableFields) {
                $term = '%' . strtolower($searchTerm) . '%';
                $query->where(function ($builder) use ($searchableFields, $term) {
                    foreach ($searchableFields as $field) {
                        $builder->orWhereRaw("LOWER({$field}) iLike ?", [$term]);
                    }
                });
            })
            ->orderBy('created_at','desc')
            ->limit($limit);

        if ($inStockOnly) {
            // For sales/transfer, only return items present in BOTH stock tables for selected warehouse.
            $stockBalanceItemIds = StockBalance::query()
                ->where('warehouse_id', $warehouseId)
                ->where('quantity', '>', 0)
                ->distinct()
                ->pluck('item_id');

            $stockMovementItemIds = StockMovement::query()
                ->where('warehouse_id', $warehouseId)
                ->distinct()
                ->pluck('item_id');

            $eligibleItemIds = $stockBalanceItemIds
                ->intersect($stockMovementItemIds)
                ->values()
                ->all();

            if (empty($eligibleItemIds)) {
                return [];
            }

            $query->whereIn('id', $eligibleItemIds);
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            return [];
        }

        $itemIds = $items->pluck('id')->all();

        $stockBalances = StockBalance::query()
            ->select(['item_id', 'warehouse_id', 'batch', 'expire_date', 'quantity', 'reserved_out', 'reserved_in'])
            ->where('warehouse_id', $warehouseId)
            ->whereIn('item_id', $itemIds)
            ->get();

        // When enforcement is on, drafted (reserved_out) stock is treated as unavailable.
        $enforceReservation = (bool) user_preference('sale.enforce_sale_stock_reservation', false);

        return $items->map(function (Item $item) use ($stockBalances, $warehouseId, $inStockOnly, $enforceReservation) {
        $itemStockBalances = $stockBalances->where('item_id', $item->id)
        ->where('warehouse_id', $warehouseId);

        $batchSummaries = [];
        $expirySummaries = [];
        $nonBatchOnHand = 0;
        $nonBatchReservedOut = 0;
        $nonBatchReservedIn = 0;
        $hasBatch = false;
        $hasExpiry = false;

        foreach ($itemStockBalances as $balance) {
            $onHand = max(0, (float) $balance->quantity);
            $reservedOut = max(0, (float) $balance->reserved_out);
            $reservedIn = max(0, (float) $balance->reserved_in);

            // Keep rows that carry a reservation even when physical quantity is zero.
            if ($onHand <= 0 && $reservedOut <= 0 && $reservedIn <= 0) {
                continue;
            }

            $batchKey = trim((string) ($balance->batch ?? ''));
            if ($batchKey !== '') {
                $hasBatch = true;
                $summary = $batchSummaries[$batchKey] ?? [
                    'batch' => $batchKey,
                    'expire_date' => $balance->expire_date,
                    'on_hand' => 0,
                    'reserved_out' => 0,
                    'reserved_in' => 0,
                    'avg_cost' => $item->avg_cost,
                ];
                $summary['on_hand'] += $onHand;
                $summary['reserved_out'] += $reservedOut;
                $summary['reserved_in'] += $reservedIn;
                $batchSummaries[$batchKey] = $summary;
            } elseif ($balance->expire_date) {
                $hasExpiry = true;
                $expiryKey = (string) $balance->expire_date;
                $summary = $expirySummaries[$expiryKey] ?? [
                    'expire_date' => $balance->expire_date,
                    'on_hand' => 0,
                    'reserved_out' => 0,
                    'reserved_in' => 0,
                    'avg_cost' => $item->avg_cost,
                ];
                $summary['on_hand'] += $onHand;
                $summary['reserved_out'] += $reservedOut;
                $summary['reserved_in'] += $reservedIn;
                $expirySummaries[$expiryKey] = $summary;
            } else {
                $nonBatchOnHand += $onHand;
                $nonBatchReservedOut += $reservedOut;
                $nonBatchReservedIn += $reservedIn;
            }
        }

        $availableFor = function (float $onHand, float $reservedOut) use ($enforceReservation) {
            return $enforceReservation ? round(max(0, $onHand - $reservedOut), 2) : round($onHand, 2);
        };

        $batches = array_map(function ($batch) use ($availableFor) {
            return [
                'batch' => $batch['batch'],
                'expire_date' => $batch['expire_date'],
                'on_hand' => round($batch['on_hand'] ?? 0, 2),
                'reserved_out' => round($batch['reserved_out'] ?? 0, 2),
                'reserved_in' => round($batch['reserved_in'] ?? 0, 2),
                'available' => $availableFor((float) ($batch['on_hand'] ?? 0), (float) ($batch['reserved_out'] ?? 0)),
                'avg_cost' => $batch['avg_cost'],
            ];
        }, $batchSummaries);

        $expiryBatches = array_map(function ($expiry) use ($availableFor) {
            return [
                'expire_date' => $expiry['expire_date'],
                'on_hand' => round($expiry['on_hand'] ?? 0, 2),
                'reserved_out' => round($expiry['reserved_out'] ?? 0, 2),
                'reserved_in' => round($expiry['reserved_in'] ?? 0, 2),
                'available' => $availableFor((float) ($expiry['on_hand'] ?? 0), (float) ($expiry['reserved_out'] ?? 0)),
                'avg_cost' => $expiry['avg_cost'],
            ];
        }, $expirySummaries);

        $totalOnHand = round(
            array_sum(array_column($batches, 'on_hand'))
            + array_sum(array_column($expiryBatches, 'on_hand'))
            + $nonBatchOnHand,
            2
        );

        $totalReservedOut = round(
            array_sum(array_column($batches, 'reserved_out'))
            + array_sum(array_column($expiryBatches, 'reserved_out'))
            + $nonBatchReservedOut,
            2
        );

        $totalReservedIn = round(
            array_sum(array_column($batches, 'reserved_in'))
            + array_sum(array_column($expiryBatches, 'reserved_in'))
            + $nonBatchReservedIn,
            2
        );

        $totalAvailable = $availableFor($totalOnHand, $totalReservedOut);

        if ($inStockOnly && $totalOnHand <= 0) {
            return null;
        }

        return [
            'id' => $item->id,
            'name' => $item->name,
            'code' => $item->code,
            'warehouse_id' => $warehouseId,
            'generic_name' => $item->generic_name,
            'packing' => $item->packing,
            'barcode' => $item->barcode,
            'unit_measure_id' => $item->unit_measure_id,
            'unitMeasure' => $item->unitMeasure,
            'brand' => $item->brand,
            'category' => $item->category,
            'colors' => $item->colors,
            'size' => $item->size,
            'purchase_price' => $item->purchase_price,
            'sale_price' => $item->sale_price,
            'margin_percentage' => $item->margin_percentage,
            'rate_a' => $item->rate_a,
            'selected_batch' => null,
            'rate_b' => $item->rate_b,
            'rate_c' => $item->rate_c,
            'rack_no' => $item->rack_no,
            'fast_search' => $item->fast_search,
            'batches' => array_values($batches ?? []),
            'expiry_batches' => array_values($expiryBatches ?? []),
            'on_hand' => $totalOnHand,
            'reserved_out' => $totalReservedOut,
            'reserved_in' => $totalReservedIn,
            'available' => $totalAvailable,
            'avg_cost' => $item->avg_cost,
            'has_batch' => $hasBatch,
            'has_expiry' => $hasExpiry,
        ];
        })->filter()->values()->all();
    }

    private function resolveItemLimit(?int $limit, string $searchTerm): int
    {
        $default = $searchTerm ? 200 : 50;
        $limit = $limit ?: $default;
        return max(1, min(250, $limit));
    }

    private function makeItemCacheKey(string $warehouseId, string $searchTerm, int $limit): string
    {
        $hash = $searchTerm ? md5($searchTerm) : 'all';
        return "items_with_batches:warehouse:{$warehouseId}:search:{$hash}:limit:{$limit}";
    }


    // private function gatherItemsForWarehouse(string $warehouseId, string $searchTerm, int $limit): array
    // {
    //     $searchableFields = ['name', 'code', 'generic_name', 'packing', 'barcode', 'fast_search'];

    //     $query = Item::query()
    //         ->select([
    //             'id',
    //             'name',
    //             'code',
    //             'generic_name',
    //             'packing',
    //             'barcode',
    //             'unit_measure_id',
    //             'brand_id',
    //             'category_id',
    //             'colors',
    //             'size_id',
    //             'purchase_price',
    //             'sale_price',
    //             'rate_a',
    //             'rate_b',
    //             'rate_c',
    //             'rack_no',
    //             'fast_search',
    //         ])
    //         ->with([
    //             'unitMeasure:id,name,unit,quantity_id',
    //             'brand:id,name',
    //             'category:id,name',
    //             'size:id,name',
    //         ])
    //         ->when($searchTerm, function ($query) use ($searchTerm, $searchableFields) {
    //             $term = '%' . strtolower($searchTerm) . '%';
    //             $query->where(function ($builder) use ($searchableFields, $term) {
    //                 foreach ($searchableFields as $field) {
    //                     $builder->orWhereRaw("LOWER({$field}) iLike ?", [$term]);
    //                 }
    //             });
    //         })
    //         ->orderBy('name')
    //         ->limit($limit);

    //     $items = $query->get();
    //     if ($items->isEmpty()) {
    //         return [];
    //     }

    //     $itemIds = $items->pluck('id')->all();

    //     $stocks = Stock::query()
    //         ->select(['id', 'item_id', 'batch', 'expire_date', 'quantity', 'unit_price'])
    //         ->where('warehouse_id', $warehouseId)
    //         ->whereIn('item_id', $itemIds)
    //         ->get();

    //     $stockOuts = StockOut::query()
    //         ->select(['stock_id', 'item_id', 'quantity'])
    //         ->where('warehouse_id', $warehouseId)
    //         ->whereIn('item_id', $itemIds)
    //         ->get()
    //         ->groupBy('stock_id')
    //         ->map(fn ($group) => (float) $group->sum('quantity'));

    //     $stocksByItem = $stocks->groupBy('item_id');

    //     return $items->map(function (Item $item) use ($stocksByItem, $stockOuts) {
    //         $itemStocks = $stocksByItem->get($item->id, collect());
    //         $batchSummaries = [];
    //         $nonBatchOnHand = 0;

    //         foreach ($itemStocks as $stock) {
    //             $available = max(0, (float) $stock->quantity - ($stockOuts[$stock->id] ?? 0));
    //             if ($available <= 0) {
    //                 continue;
    //             }

    //             $batchKey = trim((string) ($stock->batch ?? ''));
    //             if ($batchKey !== '') {
    //                 if (!isset($batchSummaries[$batchKey])) {
    //                     $batchSummaries[$batchKey] = [
    //                         'batch' => $batchKey,
    //                         'expire_date' => $stock->expire_date,
    //                         'on_hand' => 0,
    //                         'unit_price' => $stock->unit_price,
    //                     ];
    //                 }
    //                 $batchSummaries[$batchKey]['expire_date'] = $batchSummaries[$batchKey]['expire_date'] ?? $stock->expire_date;
    //                 $batchSummaries[$batchKey]['on_hand'] += $available;
    //             } else {
    //                 $nonBatchOnHand += $available;
    //             }
    //         }

    //         $batches = array_values(array_map(function ($batch) {
    //             return [
    //                 'batch' => $batch['batch'],
    //                 'expire_date' => $batch['expire_date'],
    //                 'on_hand' => round($batch['on_hand'] ?? 0, 2),
    //                 'unit_price' => $batch['unit_price'],
    //             ];
    //         }, $batchSummaries));

    //         $batchOnHand = array_reduce($batches, fn ($carry, $batch) => $carry + ($batch['on_hand'] ?? 0), 0);
    //         $totalOnHand = round($batchOnHand + $nonBatchOnHand, 2);
    //         $hasBatches = count($batches) > 0;

    //         return [
    //             'id' => $item->id,
    //             'name' => $item->name,
    //             'code' => $item->code,
    //             'generic_name' => $item->generic_name,
    //             'packing' => $item->packing,
    //             'barcode' => $item->barcode,
    //             'unit_measure_id' => $item->unit_measure_id,
    //             'unitMeasure' => $item->unitMeasure,
    //             'brand' => $item->brand,
    //             'category' => $item->category,
    //             'colors' => $item->colors,
    //             'size' => $item->size,
    //             'purchase_price' => $item->purchase_price,
    //             'sale_price' => $item->sale_price,
    //             'rate_a' => $item->rate_a,
    //             'rate_b' => $item->rate_b,
    //             'rate_c' => $item->rate_c,
    //             'rack_no' => $item->rack_no,
    //             'fast_search' => $item->fast_search,
    //             'batches' => $batches,
    //             'has_batches' => $hasBatches,
    //             'selected_batch' => null,
    //             'on_hand' => $totalOnHand,
    //             'avg_cost' => $item->avgCost(),
    //         ];
    //     })->values()->all();
    // }
    /**
     * Search currencies
     */
    private function searchCurrencies(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = Currency::query()
            ->select('id', 'name', 'code', 'symbol', 'exchange_rate')
            ->where('is_active', true)
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'code', 'symbol'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') iLike ?', [$searchTerm]);
                    }
                }
            });

        return $query->limit($limit)->get()->toArray();
    }

    /**
     * Search users
     */
    private function searchUsers(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = User::query()
            ->select('id', 'name', 'email')
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'email'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') iLike ?', [$searchTerm]);
                    }
                }
            });

        // Add additional filters if provided
        if (isset($additionalParams['branch_id'])) {
            $query->where('branch_id', $additionalParams['branch_id']);
        }

        return $query->limit($limit)->get()->toArray();
    }

    /**
     * Search branches
     */
    private function searchBranches(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = Branch::query()
            ->select('id', 'name', 'address', 'phone', 'email')
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'address', 'phone', 'email'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') iLike ?', [$searchTerm]);
                    }
                }
            });

        // Add additional filters if provided
        if (isset($additionalParams['company_id'])) {
            $query->where('company_id', $additionalParams['company_id']);
        }

        return $query->limit($limit)->get()->toArray();
    }

    /**
     * Search companies
     */
    private function searchCompanies(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = Company::query()
            ->select('id', 'name', 'email', 'phone', 'address')
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'email', 'phone', 'address'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') iLike ?', [$searchTerm]);
                    }
                }
            });

        return $query->limit($limit)->get()->toArray();
    }
    /**
     * Search unit measures
     */
    private function searchUnitMeasures(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = UnitMeasure::query()
            ->select('id', 'name', 'unit', 'symbol')
            ->where('is_active', true)
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'unit', 'symbol'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') iLike ?', [$searchTerm]);
                    }
                }
            });

        return $query->limit($limit)->get()->toArray();
    }
    /**
     * Search brands
     */
    private function searchBrands(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = Brand::query()
            ->select('id', 'name', 'legal_name', 'registration_number', 'email', 'phone', 'website', 'industry', 'type', 'city', 'country')
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'legal_name', 'registration_number', 'email', 'phone', 'website', 'industry', 'type', 'city', 'country'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') iLike ?', [$searchTerm]);
                    }
                }
            });
        return $query->limit($limit)->get()->toArray();
    }
    /**
     * Search categories
     */
    private function searchCategories(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = Category::query()
            ->select('id', 'name', 'remark')
            ->where('is_active', true)
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'remark'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') iLike ?', [$searchTerm]);
                    }
                }
            });

        return $query->limit($limit)->get()->toArray();
    }
    /**
     * Search warehouses
     */
    private function searchWarehouses(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = Warehouse::query()
            ->select('id', 'name', 'address')
            ->where('is_active', true)
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'address'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') iLike ?', [$searchTerm]);
                    }
                }
            });

        return $query->limit($limit)->get()->toArray();
    }

    /**
     * Search expense categories
     */
    private function searchExpenseCategories(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = ExpenseCategory::query()
            ->select('id', 'name', 'remarks', 'is_active')
            ->where('is_active', true)
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'remarks'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') iLike ?', [$searchTerm]);
                    }
                }
            });

        return $query->limit($limit)->get()->toArray();
    }

    /**
     * Search accounts
     */
    private function searchAccounts(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $locale = app()->getLocale();
        $query = Account::query()
            ->join('account_types', 'accounts.account_type_id', '=', 'account_types.id')
            ->select(
                'accounts.id',
                'accounts.name',
                'accounts.local_name',
                'accounts.number',
                'account_types.name as type_name',
                'account_types.slug as type_slug'
            )
            ->where('accounts.is_active', true)
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'local_name', 'number'])) {
                        $q->orWhereRaw('LOWER(accounts.' . $field . ') iLike ?', [$searchTerm]);
                    }
                }
            });

        // Filter by account type slug
        if (isset($additionalParams['type'])) {
            $query->where('account_types.slug', $additionalParams['type']);
        }

        // Filter by multiple types
        if (isset($additionalParams['types']) && is_array($additionalParams['types'])) {
            $query->whereIn('account_types.slug', $additionalParams['types']);
        }

        return $query->limit($limit)->get()->map(function ($account) use ($locale) {
            return [
                'id' => $account->id,
                'name' => $locale === 'en'
                    ? $account->name
                    : ($account->local_name ?: $account->name),
                'english_name' => $account->name,
                'local_name' => $account->local_name,
                'number' => $account->number,
                'type_name' => $account->type_name,
                'type_slug' => $account->type_slug,
            ];
        })->toArray();
    }

    /**
     * Search sizes
     */
    private function searchSizes(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = Size::query()
            ->select('id', 'name', 'code')
            ->where('is_active', true)
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'code'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') iLike ?', [$searchTerm]);
                    }
                }
            });

        return $query->limit($limit)->get()->toArray();
    }

    /**
     * Build a flat index of all searchable records for client-side fuzzy search.
     * Cached per user+branch for 5 minutes.
     */
    public function globalIndex(Request $request): JsonResponse
    {
        $user     = Auth::user();
        $branchId = $user?->branch_id ?? 'default';
        $cacheKey = 'global_search_index_v4:' . $user?->id . ':' . $branchId;

        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, now()->addMinutes(5), fn () => $this->buildGlobalIndex());

        return response()->json(['data' => $data]);
    }

    /**
     * Lightweight "suggested" lists shown in the search modal before typing.
     * Cached longer than the full index since these rankings barely change.
     */
    public function suggestions(): JsonResponse
    {
        $branchId = Auth::user()?->branch_id ?? 'default';
        $cacheKey = 'global_search_suggestions_v1:' . $branchId;

        $data = Cache::remember($cacheKey, now()->addMinutes(20), fn () => [
            'top_customers' => $this->topLedgers('customer', '/customers/'),
            'top_suppliers' => $this->topLedgers('supplier', '/suppliers/'),
            'top_accounts'  => $this->topAccounts(),
            'top_items'     => $this->topItems(),
        ]);

        return response()->json(['data' => $data]);
    }

    private function topLedgers(string $type, string $urlPrefix): array
    {
        $sub = Ledger::query()
            ->select(['id', 'name'])
            ->withStatementTotals()
            ->where('type', $type)
            ->where('is_active', true);

        return DB::query()->fromSub($sub, 'x')
            ->orderByRaw('ABS(statement_total_debit - statement_total_credit) DESC')
            ->limit(5)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id, 'name' => $l->name,
                'amount' => round(abs($l->statement_total_debit - $l->statement_total_credit), 2),
                'url' => $urlPrefix . $l->id,
            ])->all();
    }

    private function topAccounts(): array
    {
        $locale = app()->getLocale();

        $sub = Account::query()
            ->select(['id', 'name', 'local_name'])
            ->withStatementTotals()
            ->where('is_active', true);

        return DB::query()->fromSub($sub, 'x')
            ->orderByRaw('ABS(statement_total_debit - statement_total_credit) DESC')
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $locale === 'en' ? $a->name : ($a->local_name ?: $a->name),
                'amount' => round(abs($a->statement_total_debit - $a->statement_total_credit), 2),
                'url' => '/chart-of-accounts/' . $a->id,
            ])->all();
    }

    private function topItems(): array
    {
        return StockMovement::query()
            ->join('items', 'items.id', '=', 'stock_movements.item_id')
            ->selectRaw('items.id, items.name, COUNT(*) as tx_count')
            ->groupBy('items.id', 'items.name')
            ->orderByDesc('tx_count')
            ->limit(5)
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id, 'name' => $i->name,
                'amount' => (int) $i->tx_count,
                'url' => '/items/' . $i->id,
            ])->all();
    }

    private function buildGlobalIndex(): array
    {
        $results = [];

        // Customers
        try {
            Ledger::query()->select(['id', 'name', 'code', 'phone_no', 'is_active'])
                ->where('type', 'customer')->orderByDesc('created_at')->limit(400)->get()
                ->each(function ($r) use (&$results) {
                    $results[] = [
                        'id' => $r->id, 'name' => $r->name, 'code' => $r->code ?? '',
                        'type' => 'customer',
                        'subtitle' => 'Customer' . ($r->phone_no ? ' · ' . $r->phone_no : ''),
                        'url' => '/customers/' . $r->id,
                        'status' => $r->is_active ? 'active' : 'inactive',
                        'status_label' => $r->is_active ? 'Active' : 'Inactive',
                    ];
                });
        } catch (\Throwable $e) { Log::warning('[GlobalSearch] customers failed: ' . $e->getMessage()); }

        // Suppliers
        try {
            Ledger::query()->select(['id', 'name', 'code', 'phone_no', 'is_active'])
                ->where('type', 'supplier')->orderByDesc('created_at')->limit(400)->get()
                ->each(function ($r) use (&$results) {
                    $results[] = [
                        'id' => $r->id, 'name' => $r->name, 'code' => $r->code ?? '',
                        'type' => 'supplier',
                        'subtitle' => 'Supplier' . ($r->phone_no ? ' · ' . $r->phone_no : ''),
                        'url' => '/suppliers/' . $r->id,
                        'status' => $r->is_active ? 'active' : 'inactive',
                        'status_label' => $r->is_active ? 'Active' : 'Inactive',
                    ];
                });
        } catch (\Throwable $e) { Log::warning('[GlobalSearch] suppliers failed: ' . $e->getMessage()); }

        // Items
        try {
            Item::query()->select(['id', 'name', 'code'])->orderByDesc('created_at')->limit(500)->get()
                ->each(function ($r) use (&$results) {
                    $results[] = [
                        'id' => $r->id, 'name' => $r->name, 'code' => $r->code ?? '',
                        'type' => 'item',
                        'subtitle' => 'Product' . ($r->code ? ' · SKU: ' . $r->code : ''),
                        'url' => '/items/' . $r->id,
                        'status' => 'in_stock', 'status_label' => 'In stock',
                    ];
                });
        } catch (\Throwable $e) { Log::warning('[GlobalSearch] items failed: ' . $e->getMessage()); }

        // Sales
        try {
            Sale::query()->with('customer:id,name')->select(['id', 'number', 'customer_id', 'payment_status'])
                ->orderByDesc('created_at')->limit(300)->get()
                ->each(function ($r) use (&$results) {
                    $label  = $r->payment_status?->getLabel() ?? 'Pending';
                    $status = $r->payment_status?->value ?? 'unpaid';
                    $results[] = [
                        'id' => $r->id, 'name' => $r->number ?? ('INV-' . strtoupper(substr($r->id, -6))),
                        'code' => $r->number ?? '',
                        'type' => 'sale',
                        'subtitle' => 'Invoice' . ($r->customer ? ' · ' . $r->customer->name : ''),
                        'url' => '/sales/' . $r->id,
                        'status' => $status, 'status_label' => $label,
                    ];
                });
        } catch (\Throwable $e) { Log::warning('[GlobalSearch] sales failed: ' . $e->getMessage()); }

        // Purchases
        try {
            Purchase::query()->with('supplier:id,name')->select(['id', 'number', 'supplier_id', 'payment_status'])
                ->orderByDesc('created_at')->limit(300)->get()
                ->each(function ($r) use (&$results) {
                    $label  = $r->payment_status?->getLabel() ?? 'Pending';
                    $status = $r->payment_status?->value ?? 'unpaid';
                    $results[] = [
                        'id' => $r->id, 'name' => $r->number ?? ('PO-' . strtoupper(substr($r->id, -6))),
                        'code' => $r->number ?? '',
                        'type' => 'purchase',
                        'subtitle' => 'Purchase Order' . ($r->supplier ? ' · ' . $r->supplier->name : ''),
                        'url' => '/purchases/' . $r->id,
                        'status' => $status, 'status_label' => $label,
                    ];
                });
        } catch (\Throwable $e) { Log::warning('[GlobalSearch] purchases failed: ' . $e->getMessage()); }

        // Receipts
        try {
            Receipt::query()->with('ledger:id,name')->select(['id', 'number', 'ledger_id', 'date'])
                ->orderByDesc('created_at')->limit(300)->get()
                ->each(function ($r) use (&$results) {
                    $results[] = [
                        'id' => $r->id, 'name' => $r->number ?? ('RCP-' . strtoupper(substr($r->id, -6))),
                        'code' => $r->number ?? '',
                        'type' => 'receipt',
                        'subtitle' => 'Receipt' . ($r->ledger ? ' · ' . $r->ledger->name : ''),
                        'url' => '/receipts/' . $r->id,
                        'status' => null, 'status_label' => null,
                    ];
                });
        } catch (\Throwable $e) { Log::warning('[GlobalSearch] receipts failed: ' . $e->getMessage()); }

        // Payments
        try {
            Payment::query()->with('ledger:id,name')->select(['id', 'number', 'ledger_id', 'date'])
                ->orderByDesc('created_at')->limit(300)->get()
                ->each(function ($r) use (&$results) {
                    $results[] = [
                        'id' => $r->id, 'name' => $r->number ?? ('PAY-' . strtoupper(substr($r->id, -6))),
                        'code' => $r->number ?? '',
                        'type' => 'payment',
                        'subtitle' => 'Payment' . ($r->ledger ? ' · ' . $r->ledger->name : ''),
                        'url' => '/payments/' . $r->id,
                        'status' => null, 'status_label' => null,
                    ];
                });
        } catch (\Throwable $e) { Log::warning('[GlobalSearch] payments failed: ' . $e->getMessage()); }

        // Expenses
        try {
            Expense::query()->select(['id', 'date', 'remarks'])->orderByDesc('created_at')->limit(200)->get()
                ->each(function ($r) use (&$results) {
                    $name = $r->remarks ? Str::limit($r->remarks, 40) : ('Expense · ' . optional($r->date)->format('d M Y'));
                    $results[] = [
                        'id' => $r->id, 'name' => $name, 'code' => '',
                        'type' => 'expense',
                        'subtitle' => 'Expense' . ($r->date ? ' · ' . optional($r->date)->format('d M Y') : ''),
                        'url' => '/expenses/' . $r->id,
                        'status' => null, 'status_label' => null,
                    ];
                });
        } catch (\Throwable $e) { Log::warning('[GlobalSearch] expenses failed: ' . $e->getMessage()); }

        // Chart of Accounts — eager loading only (no JOIN avoids BranchSpecific/SoftDeletes scope conflicts)
        try {
            Account::query()
                ->with('accountType:id,name')
                ->select(['id', 'name', 'local_name', 'number', 'account_type_id'])
                ->where('is_active', true)
                ->limit(500)
                ->get()
                ->each(function ($r) use (&$results) {
                    // Index both English name and local name so both are searchable
                    $displayName = $r->name;
                    $localName   = $r->local_name ?? '';
                    $typeName    = $r->accountType?->name ?? '';
                    $results[] = [
                        'id'           => $r->id,
                        'name'         => $displayName,
                        'local_name'   => $localName,
                        'code'         => $r->number ?? '',
                        'type'         => 'account',
                        'subtitle'     => 'Account · ' . $typeName . ($localName ? ' · ' . $localName : ''),
                        'url'          => '/chart-of-accounts/' . $r->id,
                        'status'       => null,
                        'status_label' => null,
                    ];
                    // Also add a duplicate entry keyed on local_name so it is independently searchable
                    if ($localName) {
                        $results[] = [
                            'id'           => $r->id . '_local',
                            'name'         => $localName,
                            'local_name'   => $localName,
                            'code'         => $r->number ?? '',
                            'type'         => 'account',
                            'subtitle'     => 'Account · ' . $typeName,
                            'url'          => '/chart-of-accounts/' . $r->id,
                            'status'       => null,
                            'status_label' => null,
                        ];
                    }
                });
        } catch (\Throwable $e) { Log::warning('[GlobalSearch] accounts failed: ' . $e->getMessage()); }

        // Reports
        collect($this->globalSearchReports())->each(function ($report) use (&$results) {
            $results[] = [
                'id'           => 'report_' . $report['key'],
                'name'         => $report['name'],
                'local_name'   => $report['local_name'] ?? '',
                'code'         => $report['key'],
                'aliases'      => $report['aliases'],
                'type'         => 'report',
                'subtitle'     => 'Report · ' . $report['group'],
                'url'          => '/reports?report=' . $report['key'],
                'status'       => null,
                'status_label' => null,
            ];
        });

        // Owners
        try {
            Owner::query()->select(['id', 'name', 'is_active'])->orderByDesc('created_at')->limit(100)->get()
                ->each(function ($r) use (&$results) {
                    $results[] = [
                        'id' => $r->id, 'name' => $r->name, 'code' => '',
                        'type' => 'owner',
                        'subtitle' => 'Owner',
                        'url' => '/owners/' . $r->id,
                        'status' => ($r->is_active ?? true) ? 'active' : 'inactive',
                        'status_label' => ($r->is_active ?? true) ? 'Active' : 'Inactive',
                    ];
                });
        } catch (\Throwable $e) { Log::warning('[GlobalSearch] owners failed: ' . $e->getMessage()); }

        // Users
        try {
            User::query()->select(['id', 'name', 'email'])->limit(100)->get()
                ->each(function ($r) use (&$results) {
                    $results[] = [
                        'id' => $r->id, 'name' => $r->name, 'code' => '',
                        'type' => 'user',
                        'subtitle' => 'User · ' . $r->email,
                        'url' => '/users/' . $r->id,
                        'status' => 'active', 'status_label' => 'Active',
                    ];
                });
        } catch (\Throwable $e) { Log::warning('[GlobalSearch] users failed: ' . $e->getMessage()); }

        return $results;
    }

    private function globalSearchReports(): array
    {
        $reports = [
            ['key' => 'trial_balance', 'name' => 'Trial Balance', 'group' => 'Financial'],
            ['key' => 'balance_sheet', 'name' => 'Balance Sheet', 'group' => 'Financial'],
            ['key' => 'income_statement', 'name' => 'Income Statement', 'group' => 'Financial'],
            ['key' => 'general_ledger', 'name' => 'General Ledger', 'group' => 'Financial'],
            ['key' => 'cash_book', 'name' => 'Cash Book', 'group' => 'Financial'],
            ['key' => 'group_summary_report', 'name' => 'Group Summary Report', 'group' => 'Financial'],
            ['key' => 'day_book_report', 'name' => 'Day Book Report', 'group' => 'Financial'],
            ['key' => 'journal_book_report', 'name' => 'Journal Book Report', 'group' => 'Financial'],
            ['key' => 'receipt_report', 'name' => 'Receipt Report', 'group' => 'Cash Flow'],
            ['key' => 'payment_report', 'name' => 'Payment Report', 'group' => 'Cash Flow'],
            ['key' => 'customer_statement', 'name' => 'Customer Statement', 'group' => 'Party'],
            ['key' => 'supplier_statement', 'name' => 'Supplier Statement', 'group' => 'Party'],
            ['key' => 'sales_report', 'name' => 'Sales Report', 'group' => 'Operations'],
            ['key' => 'purchase_report', 'name' => 'Purchase Report', 'group' => 'Operations'],
            ['key' => 'inventory_stock', 'name' => 'Inventory Stock', 'group' => 'Inventory'],
            ['key' => 'stock_movement', 'name' => 'Stock Movement', 'group' => 'Inventory'],
            ['key' => 'low_stock', 'name' => 'Low Stock', 'group' => 'Inventory'],
            ['key' => 'inventory_valuation', 'name' => 'Inventory Valuation', 'group' => 'Inventory'],
            ['key' => 'batch_wise_report', 'name' => 'Batch Wise Report', 'group' => 'Inventory'],
            ['key' => 'expiry_wise_report', 'name' => 'Expiry Wise Report', 'group' => 'Inventory'],
            ['key' => 'zero_on_hand_report', 'name' => 'Zero On Hand Report', 'group' => 'Inventory'],
            ['key' => 'fast_moving_report', 'name' => 'Fast Moving Report', 'group' => 'Inventory'],
            ['key' => 'slow_moving_report', 'name' => 'Slow Moving Report', 'group' => 'Inventory'],
            ['key' => 'today_sale_purchase_closing_stock_report', 'name' => 'Today Sale Purchase Closing Stock Report', 'group' => 'Inventory'],
            ['key' => 'near_expiry_report', 'name' => 'Near Expiry Report', 'group' => 'Inventory'],
            ['key' => 'maximum_stock_report', 'name' => 'Maximum Stock Report', 'group' => 'Inventory'],
            ['key' => 'user_activity', 'name' => 'User Activity', 'group' => 'Management'],
        ];

        $localNames = [
            'trial_balance' => 'تراز آزمایشی',
            'balance_sheet' => 'بیلانس شیت',
            'income_statement' => 'سود و زیان',
            'general_ledger' => 'دفتر کل',
            'cash_book' => 'کتاب نقدی',
            'group_summary_report' => 'خلاصه گروهی حساب‌ها',
            'day_book_report' => 'دفتر روزانه',
            'journal_book_report' => 'دفتر روزنامه',
            'receipt_report' => 'گزارش رسید',
            'payment_report' => 'گزارش پرداخت',
            'customer_statement' => 'صورت‌حساب مشتری',
            'supplier_statement' => 'صورت‌حساب تأمین‌کننده',
            'sales_report' => 'گزارش فروش',
            'purchase_report' => 'گزارش خرید',
            'inventory_stock' => 'موجودی کالا',
            'stock_movement' => 'گردش موجودی',
            'low_stock' => 'موجودی کم',
            'inventory_valuation' => 'ارزش‌گذاری موجودی',
            'batch_wise_report' => 'گزارش به تفکیک بچ',
            'expiry_wise_report' => 'گزارش بر اساس انقضا',
            'zero_on_hand_report' => 'اقلام با موجودی صفر',
            'fast_moving_report' => 'گزارش اقلام پرفروش',
            'slow_moving_report' => 'گزارش اقلام کم‌گردش',
            'today_sale_purchase_closing_stock_report' => 'فروش، خرید و موجودی پایانی امروز',
            'near_expiry_report' => 'گزارش نزدیک انقضا',
            'maximum_stock_report' => 'گزارش موجودی بیش از حد',
            'user_activity' => 'فعالیت کاربران',
        ];

        return array_map(function ($report) use ($localNames) {
            $words = Str::of($report['key'])->replace('_', ' ')->toString();
            $compactKey = str_replace('_', '', $report['key']);
            $compactName = Str::of($report['name'])->replace(' ', '')->lower()->toString();
            $localName = $localNames[$report['key']] ?? '';
            $compactLocalName = Str::of($localName)->replace(' ', '')->replace('‌', '')->toString();

            $report['local_name'] = $localName;

            $report['aliases'] = array_values(array_unique([
                $report['key'],
                $compactKey,
                $words,
                $compactName,
                $localName,
                $compactLocalName,
                $report['name'] . ' report',
                'report',
                $report['group'],
            ]));

            return $report;
        }, $reports);
    }

    /**
     * Get available resource types
     */
    public function getResourceTypes(): JsonResponse
    {
        $resourceTypes = [
            'ledgers' => 'Suppliers, Customers, and other ledger accounts',
            'items' => 'Inventory items',
            'currencies' => 'Currency definitions',
            'users' => 'System users',
            'branches' => 'Company branches',
            'companies' => 'Companies',
            'unit_measures' => 'Unit measures',
            'brands' => 'Brands',
            'categories' => 'Categories',
            'warehouses' => 'Warehouses',
            'expense_categories' => 'Expense categories',
            'accounts' => 'Chart of accounts',
        ];

        return response()->json([
            'success' => true,
            'data' => $resourceTypes
        ]);
    }
}

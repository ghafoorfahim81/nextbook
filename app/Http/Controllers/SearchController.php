<?php

namespace App\Http\Controllers;

use App\Http\Resources\Inventory\ItemResource;
use App\Models\Administration\UnitMeasure;
use App\Models\Account\Account;
use App\Models\Administration\Size;
use App\Models\Administration\Currency;
use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Administration\Brand;
use App\Models\Administration\Category;
use App\Models\Administration\Store;
use App\Models\Expense\ExpenseCategory;
use App\Models\Inventory\Item;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockOut;
use App\Models\Ledger\Ledger;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
class SearchController extends Controller
{
    /**
     * Search resources by type
     */
    public function search(Request $request, string $resourceType): JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {

        // dd($request->all());
        // return response()->json([
        //     'success' => true,
        //     'data' => $request->all(),

        // ]);
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

            case 'stores':
                return $this->searchStores($searchTerm, $fields, $limit, $additionalParams);

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
    private function searchLedgers(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = Ledger::query()
            ->select('id', 'name', 'type', 'email', 'phone_no', 'address')
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'email', 'phone_no', 'address'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') iLike ?', [$searchTerm]);
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

        return $query->limit($limit)->get()->toArray();
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

        // For sales, filter items that have available stock in the specified store
        if (isset($additionalParams['store_id'])) {
            $storeId = $additionalParams['store_id'];
            $query->whereHas('stocks', function ($q) use ($storeId) {
                $q->where('store_id', $storeId)
                  ->where('quantity', '>', 0);
            });
        }

        return ItemResource::collection($query->limit($limit)->get());
    }

    /**
     * Search items for sales with store filtering and batch information
     */
    public function searchItemsForSale(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'nullable|string|exists:stores,id',
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
        $requestedStoreId = trim((string) ($request->input('store_id') ?? ''));
        $storeId = $requestedStoreId ?: Store::main()?->id;
        if (!$storeId || !Store::query()->where('id', $storeId)->first()) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found',
            ], 404);
        }

        try {
            $cacheKey = $this->makeItemCacheKey($storeId, $searchTerm, $limit);
            $items = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($storeId, $searchTerm, $limit) {
                return $this->gatherItemsForStore($storeId, $searchTerm, $limit);
            });

            return response()->json([
                'success' => true,
                'data' => $items,
                'meta' => [
                    'store_id' => $storeId,
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

    private function resolveItemLimit(?int $limit, string $searchTerm): int
    {
        $default = $searchTerm ? 200 : 50;
        $limit = $limit ?: $default;
        return max(1, min(250, $limit));
    }

    private function makeItemCacheKey(string $storeId, string $searchTerm, int $limit): string
    {
        $hash = $searchTerm ? md5($searchTerm) : 'all';
        return "items_with_batches:store:{$storeId}:search:{$hash}:limit:{$limit}";
    }

    private function gatherItemsForStore(string $storeId, string $searchTerm, int $limit): array
    {
        $searchableFields = ['name', 'code', 'generic_name', 'packing', 'barcode', 'fast_search'];

        $query = Item::query()
            ->select([
                'id',
                'name',
                'code',
                'generic_name',
                'packing',
                'barcode',
                'unit_measure_id',
                'brand_id',
                'category_id',
                'colors',
                'size_id',
                'purchase_price',
                'sale_price',
                'rate_a',
                'rate_b',
                'rate_c',
                'rack_no',
                'fast_search',
            ])
            ->with([
                'unitMeasure:id,name,unit,quantity_id',
                'brand:id,name',
                'category:id,name',
                'size:id,name',
            ])
            ->when($searchTerm, function ($query) use ($searchTerm, $searchableFields) {
                $term = '%' . strtolower($searchTerm) . '%';
                $query->where(function ($builder) use ($searchableFields, $term) {
                    foreach ($searchableFields as $field) {
                        $builder->orWhereRaw("LOWER({$field}) iLike ?", [$term]);
                    }
                });
            })
            ->orderBy('name')
            ->limit(2);

        $items = $query->get();
        if ($items->isEmpty()) {
            return [];
        }

        $itemIds = $items->pluck('id')->all();

        $stocks = Stock::query()
            ->select(['id', 'item_id', 'batch', 'expire_date', 'quantity'])
            ->where('store_id', $storeId)
            ->whereIn('item_id', $itemIds)
            ->get();

        $stockOuts = StockOut::query()
            ->select(['stock_id', 'item_id', 'quantity'])
            ->where('store_id', $storeId)
            ->whereIn('item_id', $itemIds)
            ->get()
            ->groupBy('stock_id')
            ->map(fn ($group) => (float) $group->sum('quantity'));

        $stocksByItem = $stocks->groupBy('item_id');

        return $items->map(function (Item $item) use ($stocksByItem, $stockOuts) {
            $itemStocks = $stocksByItem->get($item->id, collect());
            $batchSummaries = [];
            $nonBatchOnHand = 0;

            foreach ($itemStocks as $stock) {
                $available = max(0, (float) $stock->quantity - ($stockOuts[$stock->id] ?? 0));
                if ($available <= 0) {
                    continue;
                }

                $batchKey = trim((string) ($stock->batch ?? ''));
                if ($batchKey !== '') {
                    if (!isset($batchSummaries[$batchKey])) {
                        $batchSummaries[$batchKey] = [
                            'batch' => $batchKey,
                            'expire_date' => $stock->expire_date,
                            'on_hand' => 0,
                        ];
                    }
                    $batchSummaries[$batchKey]['expire_date'] = $batchSummaries[$batchKey]['expire_date'] ?? $stock->expire_date;
                    $batchSummaries[$batchKey]['on_hand'] += $available;
                } else {
                    $nonBatchOnHand += $available;
                }
            }

            $batches = array_values(array_map(function ($batch) {
                return [
                    'batch' => $batch['batch'],
                    'expire_date' => $batch['expire_date'],
                    'on_hand' => round($batch['on_hand'] ?? 0, 2),
                ];
            }, $batchSummaries));

            $batchOnHand = array_reduce($batches, fn ($carry, $batch) => $carry + ($batch['on_hand'] ?? 0), 0);
            $totalOnHand = round($batchOnHand + $nonBatchOnHand, 2);
            $hasBatches = count($batches) > 0;

            return [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
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
                'rate_a' => $item->rate_a,
                'rate_b' => $item->rate_b,
                'rate_c' => $item->rate_c,
                'rack_no' => $item->rack_no,
                'fast_search' => $item->fast_search,
                'batches' => $batches,
                'has_batches' => $hasBatches,
                'on_hand' => $totalOnHand,
            ];
        })->values()->all();
    }
    /**
     * Search currencies
     */
    private function searchCurrencies(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = Currency::query()
            ->select('id', 'name', 'code', 'symbol', 'exchange_rate')
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
            ->select('id', 'name', 'description')
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'description'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') iLike ?', [$searchTerm]);
                    }
                }
            });
        return $query->limit($limit)->get()->toArray();
    }
    /**
     * Search stores
     */
    private function searchStores(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = Store::query()
            ->select('id', 'name', 'address')
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
        $query = Account::query()
            ->join('account_types', 'accounts.account_type_id', '=', 'account_types.id')
            ->select('accounts.id', 'accounts.name', 'accounts.number', 'account_types.name as type_name', 'account_types.slug as type_slug')
            ->where('accounts.is_active', true)
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'number'])) {
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

        return $query->limit($limit)->get()->toArray();
    }

    /**
     * Search sizes
     */
    private function searchSizes(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = Size::query()
            ->select('id', 'name', 'code')
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
            'stores' => 'Stores',
            'expense_categories' => 'Expense categories',
            'accounts' => 'Chart of accounts',
        ];

        return response()->json([
            'success' => true,
            'data' => $resourceTypes
        ]);
    }
}

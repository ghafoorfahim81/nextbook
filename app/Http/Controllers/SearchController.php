<?php

namespace App\Http\Controllers;

use App\Http\Resources\Inventory\ItemResource;
use App\Models\Inventory\Item;
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
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:2|max:255',
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

        $searchTerm = $request->input('search');
        $fields = $request->input('fields', ['name']);
        $limit = $request->input('limit', 20);
        $additionalParams = $request->except(['search', 'fields', 'limit']);

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

            default:
                throw new \InvalidArgumentException("Unsupported resource type: {$resourceType}");
        }
    }

    /**
     * Search ledgers (suppliers, customers, etc.)
     */
    private function searchLedgers(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = DB::table('ledgers')
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
    public function searchItemsForSale(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'nullable|string|exists:items,id',
            'store_id' => 'required|string|exists:stores,id',
            'search' => 'nullable|string|min:1|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $itemId = $request->input('item_id');
        $storeId = $request->input('store_id');
        $searchTerm = $request->input('search');

        try {
            $query = Item::query()
                ->with(['unitMeasure', 'brand', 'category', 'stocks' => function ($query) use ($storeId) {
                    $query->where('store_id', $storeId)
                          ->where('quantity', '>', 0)
                          ->with('stockOuts')
                          ->orderBy('date', 'asc')
                          ->orderBy('created_at', 'asc');
                }])
                ->whereHas('stocks', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId)
                      ->where('quantity', '>', 0);
                });

            // Filter by specific item_id if provided
            if ($itemId) {
                $query->where('id', $itemId);
            }

            // Add search term filter if provided
            if ($searchTerm) {
                $searchTerm = '%' . strtolower($searchTerm) . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->whereRaw('LOWER(name) iLike ?', [$searchTerm])
                      ->orWhereRaw('LOWER(code) iLike ?', [$searchTerm])
                      ->orWhereRaw('LOWER(generic_name) iLike ?', [$searchTerm])
                      ->orWhereRaw('LOWER(barcode) iLike ?', [$searchTerm])
                      ->orWhereRaw('LOWER(fast_search) iLike ?', [$searchTerm]);
                });
            }

            $items = $query->get()->map(function ($item) use ($storeId) {
                $stocks = $item->stocks->filter(function ($stock) {
                    // Calculate available quantity (total stock - stock outs)
                    $stock->available_quantity = $stock->quantity - $stock->stockOuts->sum('quantity');
                    return $stock->available_quantity > 0;
                })->values();

                $totalAvailable = $stocks->sum('available_quantity');

                // Get unique batches with their available quantities
                $batches = $stocks->map(function ($stock) {
                    return [
                        'batch' => $stock->batch,
                        'expire_date' => $stock->expire_date?->format('Y-m-d'),
                        'available_quantity' => $stock->available_quantity,
                        'unit_price' => $stock->unit_price,
                        'cost' => $stock->cost,
                    ];
                })->filter(function ($batch) {
                    return $batch['available_quantity'] > 0;
                })->values();

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'generic_name' => $item->generic_name,
                    'packing' => $item->packing,
                    'barcode' => $item->barcode,
                    'unit_measure_id' => $item->unit_measure_id,
                    'unit_measure_name' => $item->unitMeasure?->name,
                    'brand_name' => $item->brand?->name,
                    'category_name' => $item->category?->name,
                    'purchase_price' => $item->purchase_price,
                    'cost' => $item->cost,
                    'mrp_rate' => $item->mrp_rate,
                    'rate_a' => $item->rate_a,
                    'rate_b' => $item->rate_b,
                    'rate_c' => $item->rate_c,
                    'on_hand' => $totalAvailable, // Total available quantity across all batches
                    'batches' => $batches, // Array of available batches
                    'has_batches' => count($batches) > 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $items,
                'meta' => [
                    'store_id' => $storeId,
                    'item_id' => $itemId,
                    'search_term' => $searchTerm,
                    'total' => $items->count()
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
     * Search currencies
     */
    private function searchCurrencies(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = DB::table('currencies')
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
        $query = DB::table('users')
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
        $query = DB::table('branches')
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
        $query = DB::table('companies')
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
        $query = DB::table('unit_measures')
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
        $query = DB::table('brands')
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
        $query = DB::table('categories')
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
        $query = DB::table('stores')
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
            'stores' => 'Stores'
        ];

        return response()->json([
            'success' => true,
            'data' => $resourceTypes
        ]);
    }
}

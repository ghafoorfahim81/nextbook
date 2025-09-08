<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    /**
     * Search resources by type
     */
    public function search(Request $request, string $resourceType): JsonResponse
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
    private function performSearch(string $resourceType, string $searchTerm, array $fields, int $limit, array $additionalParams): array
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
                        $q->orWhereRaw('LOWER(' . $field . ') LIKE ?', [$searchTerm]);
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
    private function searchItems(string $searchTerm, array $fields, int $limit, array $additionalParams): array
    {
        $query = DB::table('items')
            ->select('id', 'name', 'code', 'description', 'category_id', 'brand_id')
            ->where(function ($q) use ($searchTerm, $fields) {
                foreach ($fields as $field) {
                    if (in_array($field, ['name', 'code', 'description'])) {
                        $q->orWhereRaw('LOWER(' . $field . ') LIKE ?', [$searchTerm]);
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

        return $query->limit($limit)->get()->toArray();
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
                        $q->orWhereRaw('LOWER(' . $field . ') LIKE ?', [$searchTerm]);
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
                        $q->orWhereRaw('LOWER(' . $field . ') LIKE ?', [$searchTerm]);
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
                        $q->orWhereRaw('LOWER(' . $field . ') LIKE ?', [$searchTerm]);
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
                        $q->orWhereRaw('LOWER(' . $field . ') LIKE ?', [$searchTerm]);
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
            'companies' => 'Companies'
        ];

        return response()->json([
            'success' => true,
            'data' => $resourceTypes
        ]);
    }
}

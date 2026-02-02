<?php

namespace App\Http\Controllers;

use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Administration\BrandResource;
use App\Http\Resources\Administration\CategoryResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Administration\SizeResource;
use App\Http\Resources\Administration\StoreResource;
use App\Http\Resources\Administration\UnitMeasureResource;
use App\Http\Resources\Expense\ExpenseCategoryResource;
use App\Http\Resources\Inventory\ItemResource;
use App\Http\Resources\Ledger\LedgerResource;
use App\Models\Account\Account;
use App\Models\Administration\Brand;
use App\Models\Administration\Category;
use App\Models\Administration\Currency;
use App\Models\Administration\Quantity;
use App\Models\Administration\Size;
use App\Models\Administration\Store;
use App\Models\Administration\UnitMeasure;
use App\Models\Expense\ExpenseCategory;
use App\Models\Inventory\Item;
use App\Models\Ledger\Ledger;
use App\Services\TransactionService;
use App\Support\Inertia\CacheKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QuickCreateController extends Controller
{
    public function store(Request $request, string $resourceType): JsonResponse
    {
        // Normalize some alias types used in selects/search
        $resourceType = match ($resourceType) {
            'items-for-sale' => 'items',
            default => $resourceType,
        };

        try {
            return match ($resourceType) {
                'currencies' => $this->createCurrency($request),
                'brands' => $this->createBrand($request),
                'categories' => $this->createCategory($request),
                'stores' => $this->createStore($request),
                'sizes' => $this->createSize($request),
                'unit_measures' => $this->createUnitMeasure($request),
                'expense_categories' => $this->createExpenseCategory($request),
                'accounts' => $this->createAccount($request),
                'items' => $this->createItem($request),
                'ledgers' => $this->createLedger($request),
                default => response()->json([
                    'success' => false,
                    'message' => "Unsupported resource type: {$resourceType}",
                ], 422),
            };
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Quick create failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function createCurrency(Request $request): JsonResponse
    {
        Gate::authorize('create', Currency::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:currencies,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'code' => ['required', 'string', 'unique:currencies,code,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'symbol' => ['required', 'string', 'unique:currencies,symbol,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'format' => ['required', 'string', 'unique:currencies,format,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'exchange_rate' => ['required', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'is_base_currency' => ['nullable', 'boolean'],
            'flag' => ['nullable', 'string', 'unique:currencies,flag,NULL,id,deleted_at,NULL'],
        ]);

        $currency = Currency::create($validated);
        $this->forgetInertiaCache($request, ['currencies', 'home_currency']);

        return response()->json([
            'success' => true,
            'data' => (new CurrencyResource($currency))->resolve(),
        ]);
    }

    private function createBrand(Request $request): JsonResponse
    {
        Gate::authorize('create', Brand::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:brands,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'legal_name' => ['nullable', 'string'],
            'registration_number' => ['nullable', 'string'],
            'logo' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'website' => ['nullable', 'url'],
            'industry' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'country' => ['nullable', 'string'],
        ]);

        $brand = Brand::create($validated);
        $this->forgetInertiaCache($request, ['brands']);

        return response()->json([
            'success' => true,
            'data' => (new BrandResource($brand))->resolve(),
        ]);
    }

    private function createCategory(Request $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:categories,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'parent_id' => ['nullable', 'string', 'exists:categories,id'],
            'remark' => ['nullable', 'string'],
        ]);

        $category = Category::create($validated);
        $this->forgetInertiaCache($request, ['categories']);

        return response()->json([
            'success' => true,
            'data' => (new CategoryResource($category))->resolve(),
        ]);
    }

    private function createStore(Request $request): JsonResponse
    {
        Gate::authorize('create', Store::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:stores,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'address' => ['nullable', 'string'],
            'is_main' => ['nullable', 'boolean'],
        ]);

        $store = Store::create($validated);
        $this->forgetInertiaCache($request, ['stores']);

        return response()->json([
            'success' => true,
            'data' => (new StoreResource($store))->resolve(),
        ]);
    }

    private function createSize(Request $request): JsonResponse
    {
        Gate::authorize('create', Size::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:sizes,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'code' => ['required', 'string', 'unique:sizes,code,NULL,id,branch_id,NULL,deleted_at,NULL'],
        ]);

        $size = Size::create($validated);
        $this->forgetInertiaCache($request, ['sizes']);

        return response()->json([
            'success' => true,
            'data' => (new SizeResource($size))->resolve(),
        ]);
    }

    private function createExpenseCategory(Request $request): JsonResponse
    {
        Gate::authorize('create', ExpenseCategory::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'remarks' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (!array_key_exists('is_active', $validated)) {
            $validated['is_active'] = true;
        }

        $category = ExpenseCategory::create($validated);

        return response()->json([
            'success' => true,
            'data' => (new ExpenseCategoryResource($category))->resolve(),
        ]);
    }

    private function createUnitMeasure(Request $request): JsonResponse
    {
        Gate::authorize('create', UnitMeasure::class);

        $validated = $request->validate([
            'metric' => ['required', 'array'],
            'metric.name' => ['required', 'string'],
            'metric.unit' => ['required', 'string'],
            'metric.symbol' => ['required', 'string'],
            'measure' => ['required', 'array'],
            'measure.name' => ['required', 'string'],
            'measure.unit' => ['required', 'numeric'],
            'measure.symbol' => ['required', 'string'],
        ]);

        /** @var array{name:string,unit:string,symbol:string} $metricType */
        $metricType = $validated['metric'];
        /** @var array{name:string,unit:numeric,symbol:string} $measure */
        $measure = $validated['measure'];

        $unitMeasure = DB::transaction(function () use ($metricType, $measure) {
            $quantity = Quantity::query()->where('unit', $metricType['unit'])->first();

            if (!$quantity) {
                $quantity = Quantity::create([
                    'quantity' => $metricType['name'],
                    'unit' => $metricType['unit'],
                    'symbol' => $metricType['symbol'],
                    'slug' => Str::slug($metricType['name']),
                    'is_main' => false,
                ]);
            }

            // Create measure under this quantity (UnitMeasure model)
            return $quantity->measures()->create([
                'name' => $measure['name'],
                'unit' => $measure['unit'],
                'symbol' => $measure['symbol'],
                'quantity_id' => $quantity->id,
                'is_main' => false,
            ]);
        });

        $this->forgetInertiaCache($request, ['unit_measures']);

        return response()->json([
            'success' => true,
            'data' => (new UnitMeasureResource($unitMeasure))->resolve(),
        ]);
    }

    private function createItem(Request $request): JsonResponse
    {
        Gate::authorize('create', Item::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:items,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'code' => ['required', 'string', 'unique:items,code,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'item_type' => ['nullable', 'string'],
            'unit_measure_id' => ['required', 'string', 'exists:unit_measures,id'],
            'brand_id' => ['nullable', 'string', 'exists:brands,id'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'size_id' => ['nullable', 'string', 'exists:sizes,id'],
            'sale_price' => ['required', 'numeric'],
        ]);

        if (!isset($validated['item_type']) || $validated['item_type'] === '') {
            // match the default behavior used across the app
            $validated['item_type'] = \App\Enums\ItemType::INVENTORY_MATERIALS->value;
        }

        $item = Item::create($validated);
        $this->forgetInertiaCache($request, ['items']);

        return response()->json([
            'success' => true,
            'data' => (new ItemResource($item->fresh()))->resolve(),
        ]);
    }

    private function createLedger(Request $request): JsonResponse
    {
        Gate::authorize('create', Ledger::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'phone_no' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
        ]);

        if (!isset($validated['type']) || $validated['type'] === '') {
            $validated['type'] = 'customer';
        }

        $ledger = Ledger::create($validated);
        $this->forgetInertiaCache($request, ['ledgers']);

        return response()->json([
            'success' => true,
            'data' => (new LedgerResource($ledger))->resolve(),
        ]);
    }

    private function createAccount(Request $request): JsonResponse
    {
        Gate::authorize('create', Account::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:accounts,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'number' => ['required', 'string', 'unique:accounts,number,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'account_type_id' => ['required', 'string', 'exists:account_types,id'],
            'is_active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],
            'currency_id' => ['required', 'string', 'exists:currencies,id'],
            'rate' => ['required', 'numeric'],
            'amount' => ['required', 'numeric'],
            'transaction_type' => ['required', 'string', Rule::in(\App\Enums\TransactionType::values())],
        ]);

        $account = DB::transaction(function () use ($validated) {
            $validated['slug'] = Str::slug($validated['name']);
            $account = Account::create($validated);

            $glAccounts = Cache::get('gl_accounts');
            $transactionService = app(TransactionService::class);

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $validated['currency_id'],
                    'rate' => (float) ($validated['rate'] ?? 1),
                    'date' => now(),
                    'reference_type' => Account::class,
                    'reference_id' => $account->id,
                    'remark' => 'Opening balance for account ' . $account->name,
                ],
                lines: [
                    [
                        'account_id' => $account->id,
                        'debit' => 0,
                        'credit' => (float) $validated['amount'],
                        'remark' => 'Opening balance for account ' . $account->name,
                    ],
                    [
                        'account_id' => $glAccounts['opening-balance-equity'] ?? null,
                        'debit' => (float) $validated['amount'],
                        'credit' => 0,
                        'remark' => 'Opening balance for account ' . $account->name,
                    ],
                ],
            );

            $account->opening()->create([
                'transaction_id' => $transaction->id,
            ]);

            return $account;
        });

        $this->forgetInertiaCache($request, ['accounts', 'gl_accounts']);

        return response()->json([
            'success' => true,
            'data' => (new AccountResource($account->fresh()))->resolve(),
        ]);
    }

    /**
     * @param array<int, string> $names
     */
    private function forgetInertiaCache(Request $request, array $names): void
    {
        foreach ($names as $name) {
            Cache::forget(CacheKey::forCompanyBranchLocale($request, $name));
        }
    }
}


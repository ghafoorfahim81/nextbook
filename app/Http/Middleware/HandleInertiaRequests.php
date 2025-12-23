<?php

namespace App\Http\Middleware;

use App\Http\Resources\Administration\BranchResource;
use App\Http\Resources\Administration\CategoryResource;
use App\Http\Resources\Administration\BrandResource;
use App\Http\Resources\Administration\StoreResource;
use App\Http\Resources\Administration\UnitMeasureResource;
use App\Models\Administration\Branch;
use App\Models\Administration\Category;
use App\Http\Resources\Account\AccountResource;
use App\Models\Account\Account;
use App\Http\Resources\Account\AccountTypeResource;
use App\Models\Account\AccountType;
use App\Models\Administration\Brand;
use App\Models\Administration\Currency;
use App\Models\Administration\Store;
use App\Http\Resources\Ledger\LedgerResource;
use App\Http\Resources\UserManagement\RoleResource;
use App\Models\Role;
use App\Models\Ledger\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use App\Enums\BusinessType;
use App\Enums\CalendarType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use App\Http\Resources\Administration\CurrencyResource;
use App\Enums\SalesPurchaseType;
use App\Http\Resources\Inventory\ItemResource;
use App\Models\Inventory\Item;
use App\Enums\DiscountType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $cacheDuration = 60 * 60;

        $categories = Cache::remember(
            'categories',
            $cacheDuration,
            fn() => CategoryResource::collection(
                Category::latest()->take(10)->get()
            )
        );

        $accounts = Cache::remember(
            'accounts',
            $cacheDuration,
            fn() => AccountResource::collection(
                Account::latest()->take(1000)->get()
            )
        );

        $accountTypes = Cache::remember(
            'accountTypes',
            $cacheDuration,
            fn() => AccountTypeResource::collection(
                AccountType::latest()->take(10)->get()
            )
        );
        $branches = Cache::remember(
            'branches',
            $cacheDuration,
            fn() => BranchResource::collection(
                Branch::latest()->take(10)->get()
            )
        );

        $stores = Cache::remember(
            'stores',
            $cacheDuration,
            fn() => StoreResource::collection(
                Store::latest()->take(10)->get()
            )
        );

        $currencies = Cache::remember(
            'currencies',
            $cacheDuration,
            fn() => CurrencyResource::collection(
                Currency::latest()->get()
            )
        );

        $brands = Cache::remember(
            'brands',
            $cacheDuration,
            fn() => BrandResource::collection(
                Brand::latest()->take(10)->get()
            )
        );

        $unitMeasures = Cache::remember(
            'unitMeasures',
            $cacheDuration,
            fn() => UnitMeasureResource::collection(
                \App\Models\Administration\UnitMeasure::latest()->take(1000)->get()
            )
        );

        $businessTypes = Cache::rememberForever(
            'businessTypes_' . app()->getLocale(),
            fn() => collect(BusinessType::cases())->map(fn($item): array => [
                'id' => $item->value,
                'name' => $item->getLabel(),
            ])
        );

        $calendarTypes = Cache::rememberForever(
            'calendarTypes_' . app()->getLocale(),
            fn() => collect(CalendarType::cases())->map(fn($item): array => [
                'id' => $item->value,
                'name' => $item->getLabel(),
            ])
        );

        $workingStyles = Cache::rememberForever(
            'workingStyles_' . app()->getLocale(),
            fn() => collect(WorkingStyle::cases())->map(fn($item): array => [
                'id' => $item->value,
                'name' => $item->getLabel(),
            ])
        );

        $locales = Cache::rememberForever(
            'locales_' . app()->getLocale(),
            fn() => collect(Locale::cases())->map(fn($item): array => [
                'id' => $item->value,
                'name' => $item->getLabel(),
            ])
        );

        $ledgers = Cache::remember('ledgers', $cacheDuration, function () {
            return Ledger::latest()->take(1000)->get();
        });
        $salePurchaseTypes = Cache::rememberForever(
            'salePurchaseTypes_' . app()->getLocale(),
            fn() => collect(SalesPurchaseType::cases())->map(fn($item): array => [
                'id' => $item->value,
                'name' => $item->getLabel(),
            ])
        );
        $items = Cache::remember(
            'items',
            $cacheDuration,
            fn() => ItemResource::collection(
                Item::latest()->take(10)->get()
            )
        );
        $discountTypes = Cache::rememberForever(
            'discountTypes_' . app()->getLocale(),
            fn() => collect(DiscountType::cases())->map(fn($item): array => [
                'id' => $item->value,
                'name' => $item->getLabel(),
            ])
        );
        $transactionStatuses = Cache::rememberForever(
            'transactionStatuses_' . app()->getLocale(),
            fn() => collect(TransactionStatus::cases())->map(fn($item): array => [
                'id' => $item->value,
                'name' => $item->getLabel(),
            ])
        );

        $glAccounts = Cache::rememberForever('gl_accounts', function () {
            return Account::whereIn('slug', [
                'sales-revenue',
                'account-receivable',
                'cash-in-hand',
                'cost-of-goods-sold',
                'inventory-asset',
                'opening-balance-equity',
            ])->pluck('id', 'slug');
        });

        $capitalAccounts = Cache::rememberForever('capital_accounts', function () {
            return Account::join('account_types', 'accounts.account_type_id', '=', 'account_types.id')
            ->where('account_types.slug', 'equity')
            ->select('accounts.id', 'accounts.name')
            ->get();
        });

        $drawingAccounts = Cache::rememberForever('drawing_accounts', function () {
            return Account::join('account_types', 'accounts.account_type_id', '=', 'account_types.id')
            ->where('account_types.slug', 'equity')
            ->select('accounts.id', 'accounts.name')
            ->get();
        });

        $roles = Cache::rememberForever('roles', function () {
            return Role::latest()->take(10)->get();
        });

        $user_preferences  = Cache::rememberForever('user_preferences', function () use($request) {
            return $request->user()?->preferences;
        });
        $homeCurrency = Cache::rememberForever('home_currency', function () {
            return Currency::where('is_base_currency', true)->first();
        });

        $transactionTypes = Cache::rememberForever('transaction_types', function () {
            return collect(TransactionType::cases())->map(fn($item): array => [
                'id' => $item->value,
                'name' => $item->name,
            ]);
        });

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'categories' => $categories,
            'accounts' => $accounts,
            'accountTypes' => $accountTypes,
            'branches' => $branches,
            'currencies' => $currencies,
            'stores' => $stores,
            'brands' => $brands,
            'glAccounts' => $glAccounts,
            'unitMeasures' => $unitMeasures,
            'businessTypes' => $businessTypes,
            'calendarTypes' => $calendarTypes,
            'workingStyles' => $workingStyles,
            'locales' => $locales,
            'ledgers' => LedgerResource::collection($ledgers),
            'salePurchaseTypes' => $salePurchaseTypes,
            'items' => $items,
            'discountTypes' => $discountTypes,
            'transactionStatuses' => $transactionStatuses,
            'capitalAccounts' => $capitalAccounts,
            'drawingAccounts' => $drawingAccounts,
            'roles' => $roles,
            'user_preferences' => $user_preferences,
            'homeCurrency' => $homeCurrency,
            'transactionTypes' => $transactionTypes,
        ];
    }
}

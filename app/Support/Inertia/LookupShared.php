<?php

namespace App\Support\Inertia;

use App\Enums\BusinessType;
use App\Enums\CalendarType;
use App\Enums\DiscountType;
use App\Enums\Locale;
use App\Enums\SalesPurchaseType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WorkingStyle;
use App\Http\Resources\Administration\BranchResource;
use App\Http\Resources\Administration\BrandResource;
use App\Http\Resources\Administration\CategoryResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Administration\SizeResource;
use App\Http\Resources\Administration\StoreResource;
use App\Http\Resources\Administration\UnitMeasureResource;
use App\Models\Administration\Branch;
use App\Models\Administration\Brand;
use App\Models\Administration\Category;
use App\Models\Administration\Currency;
use App\Models\Administration\Size;
use App\Models\Administration\Store;
use App\Models\Administration\UnitMeasure;
use App\Enums\ItemType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Account\Account;
final class LookupShared
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Request $request): array
    {
        $cacheDuration = 60 * 60;

        $mainBranch = Cache::remember(
            CacheKey::forCompanyBranchLocale($request, 'main_branch'),
            $cacheDuration,
            fn() => Branch::query()->where('is_main', true)->first()
        );

        $categories = Cache::remember(
            CacheKey::forCompanyBranchLocale($request, 'categories'),
            $cacheDuration,
            fn() => CategoryResource::collection(
                Category::query()->where('is_active', true)->orderBy('id')->limit(10)->get()
            )
        );

        $branches = Cache::remember(
            CacheKey::forCompanyBranchLocale($request, 'branches'),
            $cacheDuration,
            fn() => BranchResource::collection(
                Branch::query()->orderBy('id')->limit(10)->get()
            )
        );

        $currencies = Cache::remember(
            CacheKey::forCompanyBranchLocale($request, 'currencies'),
            $cacheDuration,
            fn() => CurrencyResource::collection(
                Currency::query()->where('is_active', true)->orderBy('id')->get()
            )
        );

        $stores = Cache::remember(
            CacheKey::forCompanyBranchLocale($request, 'stores'),
            $cacheDuration,
            fn() => StoreResource::collection(
                Store::query()->where('is_active', true)->orderBy('id')->limit(10)->get()
            )
        );

        $brands = Cache::remember(
            CacheKey::forCompanyBranchLocale($request, 'brands'),
            $cacheDuration,
            fn() => BrandResource::collection(
                Brand::query()->orderBy('id')->limit(10)->get()
            )
        );

        $unitMeasures = Cache::remember(
            CacheKey::forCompanyBranchLocale($request, 'unit_measures'),
            $cacheDuration,
            fn() => UnitMeasureResource::collection(
                UnitMeasure::query()->where('is_active', true)->orderBy('id')->limit(1000)->get()
            )
        );

        $sizes = Cache::remember(
            CacheKey::forCompanyBranchLocale($request, 'sizes'),
            $cacheDuration,
            fn() => SizeResource::collection(
                Size::query()->where('is_active', true)->orderBy('id')->limit(10)->get()
            )
        );

        $homeCurrency = Cache::remember(
            CacheKey::forCompanyBranchLocale($request, 'home_currency'),
            $cacheDuration,
            fn() => Currency::query()->where('is_base_currency', true)->first()
        );
        Cache::put('home_currency', $homeCurrency);

        $glAccounts = Cache::remember(
            CacheKey::forCompanyBranchLocale($request, 'gl_accounts'),
            $cacheDuration,
            fn() => Account::query()->whereIn('slug', [
                'sales-revenue',
                'accounts-receivable',
                'accounts-payable',
                'cash',
                'cost-of-goods-sold',
                'inventory-stock',
                'retained-earnings',
                'opening-balance-equity',
                'non-inventory-items',
                'raw-materials',
                'finished-goods',
            ])->pluck('id', 'slug')
        ); 
        return [
            'mainBranch' => $mainBranch,
            'categories' => $categories,
            'branches' => $branches,
            'currencies' => $currencies,
            'stores' => $stores,
            'brands' => $brands,
            'unitMeasures' => $unitMeasures,
            'sizes' => $sizes,
            'homeCurrency' => $homeCurrency,
            'businessTypes' => EnumOptions::forLocale($request, BusinessType::class, 'business_types'),
            'calendarTypes' => EnumOptions::forLocale($request, CalendarType::class, 'calendar_types'),
            'workingStyles' => EnumOptions::forLocale($request, WorkingStyle::class, 'working_styles'),
            'locales' => EnumOptions::forLocale($request, Locale::class, 'locales'),
            'salePurchaseTypes' => EnumOptions::forLocale($request, SalesPurchaseType::class, 'sale_purchase_types'),
            'discountTypes' => EnumOptions::forLocale($request, DiscountType::class, 'discount_types'),
            'itemTypes' => EnumOptions::forLocale($request, ItemType::class, 'item_types'),
            'transactionStatuses' => EnumOptions::forLocale($request, TransactionStatus::class, 'transaction_statuses'),
            'transactionTypes' => EnumOptions::forLocale($request, TransactionType::class, 'transaction_types'),
        ];
    }
}

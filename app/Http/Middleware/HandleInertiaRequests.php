<?php

namespace App\Http\Middleware;

use App\Http\Resources\Administration\BranchResource;
use App\Http\Resources\Administration\CategoryResource;
use App\Http\Resources\Administration\CompanyResource;
use App\Http\Resources\Administration\StoreResource;
use App\Http\Resources\Administration\UnitMeasureResource;
use App\Models\Administration\Branch;
use App\Models\Administration\Category;
use App\Http\Resources\Account\AccountResource;
use App\Models\Account\Account;
use App\Http\Resources\Account\AccountTypeResource;
use App\Models\Account\AccountType;
use App\Models\Administration\Company;
use App\Models\Administration\Currency;
use App\Models\Administration\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

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



        $categories = Cache::remember('categories', $cacheDuration,
            fn () => CategoryResource::collection(
                Category::latest()->take(10)->get()
            ));

        $accounts = Cache::remember('accounts', $cacheDuration,
            fn () => AccountResource::collection(
                Account::latest()->take(10)->get()
            ));

        $accountTypes = Cache::remember('accountTypes', $cacheDuration,
            fn () => AccountTypeResource::collection(
                AccountType::latest()->take(10)->get()
            ));
        $branches = Cache::remember('branches', $cacheDuration,
            fn () => BranchResource::collection(
                Branch::latest()->take(10)->get()
            ));

        $stores = Cache::remember('stores', $cacheDuration,
            fn () => StoreResource::collection(
                Store::latest()->take(10)->get()
            ));

        $currencies = Cache::remember('currencies', $cacheDuration,
            fn () => CategoryResource::collection(
                Currency::latest()->take(10)->get()
            ));

        $companies = Cache::remember('companies', $cacheDuration,
            fn () => CompanyResource::collection(
                Company::latest()->take(10)->get()
            ));

        $unitMeasures = Cache::remember('unitMeasures', $cacheDuration,
            fn () => UnitMeasureResource::collection(
                \App\Models\Administration\UnitMeasure::latest()->take(10)->get()
            ));

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'categories' => $categories,
            'accounts' => $accounts,
            'accountTypes' => $accountTypes,
            'branches' => $branches,
            'currencies' => $currencies,
            'stores' => $stores,
            'companies' => $companies,
            'unitMeasures' => $unitMeasures
        ];

    }
}

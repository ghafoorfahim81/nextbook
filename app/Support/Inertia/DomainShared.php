<?php

namespace App\Support\Inertia;

use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Account\AccountTypeResource;
use App\Http\Resources\Inventory\ItemResource;
use App\Http\Resources\Ledger\LedgerResource;
use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Inventory\Item;
use App\Models\Ledger\Ledger;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

final class DomainShared
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Request $request): array
    {
        $cacheDuration = 60 * 60;

        return [
            'accounts' => Inertia::lazy(fn() => Cache::remember(
                CacheKey::forCompanyBranchLocale($request, 'accounts'),
                $cacheDuration,
                fn() => AccountResource::collection(
                    Account::query()->orderBy('id')->limit(1000)->get()
                )
            )),
            'accountTypes' => Inertia::lazy(fn() => Cache::remember(
                CacheKey::forCompanyBranchLocale($request, 'account_types'),
                $cacheDuration,
                fn() => AccountTypeResource::collection(
                    AccountType::query()->orderBy('id')->limit(10)->get()
                )
            )),
            'ledgers' => Inertia::lazy(fn() => Cache::remember(
                CacheKey::forCompanyBranchLocale($request, 'ledgers'),
                $cacheDuration,
                fn() => LedgerResource::collection(
                    Ledger::query()->orderBy('id')->limit(1000)->get()
                )
            )),
            'items' => Inertia::lazy(fn() => Cache::remember(
                CacheKey::forCompanyBranchLocale($request, 'items'),
                $cacheDuration,
                fn() => ItemResource::collection(
                    Item::query()->orderBy('id')->limit(10)->get()
                )
            )),
            'roles' => Inertia::lazy(fn() => Cache::remember(
                CacheKey::forCompanyBranchLocale($request, 'roles'),
                $cacheDuration,
                fn() => Role::query()->orderBy('id')->limit(10)->get()
            )),  
        ];
    }
}

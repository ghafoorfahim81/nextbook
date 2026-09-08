<?php

namespace App\Support\Inertia;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

final class EnumOptions
{
    /**
     * Every `$cacheName` passed to forLocale(), so the whole set can be dropped
     * on a language switch. Keep in sync with App\Support\Inertia\LookupShared.
     */
    public const CACHE_NAMES = [
        'business_types',
        'calendar_types',
        'working_styles',
        'locales',
        'costing_methods',
        'sale_purchase_types',
        'discount_types',
        'item_types',
        'transaction_statuses',
        'transaction_types',
    ];

    /**
     * @template TEnum of UnitEnum
     *
     * @param class-string<TEnum> $enumClass
     * @return array<int, array{id: mixed, name: string}>
     */
    public static function forLocale(Request $request, string $enumClass, string $cacheName): array
    {
        return Cache::rememberForever(
            CacheKey::forCompanyBranchLocale($request, "enum:{$cacheName}"),
            function () use ($enumClass): array {
                return collect($enumClass::cases())
                    ->map(static fn ($item): array => [
                        'id' => $item->value,
                        'name' => $item->getLabel(),
                    ])
                    ->all();
            }
        );
    }

    /**
     * Drop every cached enum-option list for the given locales, so the labels
     * are rebuilt in the new language on the next request.
     *
     * @param  array<int, string>  $locales
     */
    public static function forgetForLocales(Request $request, array $locales): void
    {
        $companyId = CacheKey::companyId($request) ?? 'none';
        $branchId = CacheKey::branchId($request) ?? 'none';

        foreach (array_unique(array_filter($locales)) as $locale) {
            foreach (self::CACHE_NAMES as $name) {
                Cache::forget(CacheKey::build($companyId, $branchId, $locale, "enum:{$name}"));
            }
        }
    }
}

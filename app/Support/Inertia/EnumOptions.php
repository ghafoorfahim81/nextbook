<?php

namespace App\Support\Inertia;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

final class EnumOptions
{
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
}

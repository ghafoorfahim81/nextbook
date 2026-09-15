<?php

namespace App\Enums;

/**
 * What a discount rule targets.
 *
 * Ordered from broadest to narrowest; `specificity()` is what decides which
 * rule wins when several match the same sale line.
 */
enum DiscountScope: string
{
    case ALL = 'all';
    case BRAND = 'brand';
    case CATEGORY = 'category';
    case ITEM = 'item';

    public function getLabel(): string
    {
        return match ($this) {
            self::ALL => __('enums.discount_scope.all'),
            self::BRAND => __('enums.discount_scope.brand'),
            self::CATEGORY => __('enums.discount_scope.category'),
            self::ITEM => __('enums.discount_scope.item'),
        };
    }

    /**
     * Higher wins. A rule naming one item beats a rule covering its whole
     * category, which beats a blanket rule — the same way a hand-typed line
     * discount beats all of them.
     */
    public function specificity(): int
    {
        return match ($this) {
            self::ITEM => 4,
            self::CATEGORY => 3,
            self::BRAND => 2,
            self::ALL => 1,
        };
    }

    /** ALL is the only scope that targets nothing in particular. */
    public function requiresTarget(): bool
    {
        return $this !== self::ALL;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

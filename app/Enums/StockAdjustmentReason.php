<?php

namespace App\Enums;

enum StockAdjustmentReason: string
{
    // OUT — stock decreases
    case DAMAGE = 'damage';
    case EXPIRY = 'expiry';
    case THEFT = 'theft';
    case LOSS = 'loss';
    case COUNT_DOWN = 'count_down';
    case INTERNAL_USE = 'internal_use';
    case SAMPLE = 'sample';
    case WASTAGE = 'wastage';
    case QUALITY_REJECTION = 'quality_rejection';

    // IN — stock increases
    case COUNT_UP = 'count_up';
    case FOUND = 'found';
    case OPENING_STOCK = 'opening_stock';
    case PRODUCTION_OUTPUT = 'production_output';
    case SURPLUS = 'surplus';

    public function direction(): StockMovementType
    {
        return match ($this) {
            self::COUNT_UP,
            self::FOUND,
            self::OPENING_STOCK,
            self::PRODUCTION_OUTPUT,
            self::SURPLUS => StockMovementType::IN,
            default => StockMovementType::OUT,
        };
    }

    /**
     * Default offset account slug per reason: real economic losses post to
     * 9040 Shrinkage & Wastage, neutral record corrections to 9050 Inventory
     * Adjustments. Overridable per-user in Preferences.
     */
    public function defaultOffsetAccountSlug(): string
    {
        return match ($this) {
            self::DAMAGE,
            self::EXPIRY,
            self::THEFT,
            self::LOSS,
            self::WASTAGE,
            self::QUALITY_REJECTION,
            self::INTERNAL_USE,
            self::SAMPLE => 'inventory-shrinkage-and-wastage',
            default => 'inventory-adjustments',
        };
    }

    public function getLabel(): string
    {
        return __('enums.stock_adjustment_reason.' . $this->value);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Options payload for the frontend: value, label, direction and default
     * offset-account slug for each reason.
     */
    public static function options(): array
    {
        return array_map(fn (self $reason) => [
            'id' => $reason->value,
            'name' => $reason->getLabel(),
            'direction' => $reason->direction()->value,
            'default_offset_slug' => $reason->defaultOffsetAccountSlug(),
        ], self::cases());
    }

    public static function defaultAccountMapping(): array
    {
        $mapping = [];
        foreach (self::cases() as $reason) {
            $mapping[$reason->value] = $reason->defaultOffsetAccountSlug();
        }

        return $mapping;
    }
}

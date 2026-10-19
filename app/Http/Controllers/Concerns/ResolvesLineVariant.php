<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Inventory\Item;
use App\Services\ItemVariantService;

/**
 * Resolves the variant a transaction line refers to.
 *
 * Every form now sends variant_id directly. A caller that omits it (an older
 * screen, an import) falls back to the item's default variant, exactly like
 * StockService resolves it for the stock movement itself, so the line and
 * the stock it moved always agree on which variant that was.
 */
trait ResolvesLineVariant
{
    protected function resolveLineVariantId(array $item): ?string
    {
        if (! empty($item['variant_id'])) {
            return $item['variant_id'];
        }

        $itemModel = Item::find($item['item_id'] ?? null);

        if (! $itemModel) {
            return null;
        }

        return app(ItemVariantService::class)->ensureDefault($itemModel)->id;
    }
}

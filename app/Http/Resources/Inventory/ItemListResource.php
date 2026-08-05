<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Row shape for the item index.
 *
 * `on_hand` and `variants_count` arrive as query aggregates (withSum /
 * withCount) rather than being computed per row — the previous version called
 * $this->onHand(), which issued one SUM query for every row on the page.
 *
 * @mixin \App\Models\Inventory\Item
 */
class ItemListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'measure' => $this->unitMeasure?->name,
            'unitMeasure' => $this->unitMeasure,
            'category' => $this->category?->name,
            // The index declares these two columns but the resource never
            // sent them, so they rendered blank on every row.
            'brand_name' => $this->brand?->name,
            'sale_price' => $this->sale_price,
            'purchase_price' => $this->purchase_price,
            'cost' => $this->cost,
            'avg_cost' => $this->avg_cost,
            'on_hand' => (float) ($this->on_hand ?? 0),
            'variants_count' => (int) ($this->variants_count ?? 0),
            'is_active' => (bool) $this->is_active,
            'stock_status' => $this->stockStatus(),
        ];
    }

    /**
     * Reorder signal for the row badge, from the aggregate already loaded
     * rather than a second query.
     */
    private function stockStatus(): string
    {
        $onHand = (float) ($this->on_hand ?? 0);

        if ($onHand <= 0) {
            return 'out_of_stock';
        }

        $minimum = $this->minimum_stock;

        if ($minimum !== null && $onHand <= (float) $minimum) {
            return 'low_stock';
        }

        return 'in_stock';
    }
}

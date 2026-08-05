<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Inventory\ItemVariant */
class ItemVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            // Explicit accessor: `attributes` is also Eloquent's internal
            // storage property, so the magic getter is ambiguous here.
            'attributes' => (object) ($this->getAttribute('attributes') ?? []),
            'variant_key' => $this->variant_key,
            'name' => $this->name,
            'display_name' => $this->displayName(),
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'sale_price' => $this->sale_price,
            'purchase_price' => $this->purchase_price,
            'avg_cost' => $this->avg_cost,
            'minimum_stock' => $this->minimum_stock,
            'maximum_stock' => $this->maximum_stock,
            'weight' => $this->weight,
            'sort_order' => $this->sort_order,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'on_hand' => $this->whenLoaded('stockBalances', fn () => (float) $this->stockBalances->sum('quantity')),
        ];
    }
}

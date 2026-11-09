<?php

namespace App\Http\Resources\Inventory;

use App\Http\Resources\Purchase\PurchaseItemResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LandedCostItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'landed_cost_id' => $this->landed_cost_id,
            'purchase_item_id' => $this->purchase_item_id,
            'item_id' => $this->item_id,
            'purchase_id' => $this->purchaseItem?->purchase_id,
            'purchase_number' => $this->purchaseItem?->purchase?->number,
            'item_name' => $this->item?->name,
            'item_code' => $this->item?->code,
            'variant_name' => $this->purchaseItem?->distinctVariantName(),
            'purchase_item' => PurchaseItemResource::make($this->whenLoaded('purchaseItem')),
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'weight' => $this->weight,
            'volume' => $this->volume,
            // What the edit form needs to re-split a saved row by weight, volume
            // or quantity. Stored weight is 0 unless the row was saved by weight,
            // so the form cannot work from that alone. Edit screen only: it costs
            // a unit lookup per row.
            $this->mergeWhen($request->routeIs('landed-costs.edit'), fn () => [
                'base_quantity' => $this->purchaseItem?->baseQuantity() ?? (float) $this->quantity,
                'item_weight' => (float) $this->item?->weight,
                'item_weight_unit_id' => $this->item?->weight_unit_id ?? '',
                'item_volume' => (float) $this->item?->length * (float) $this->item?->width * (float) $this->item?->height,
                'item_dimension_unit_id' => $this->item?->dimension_unit_id ?? '',
            ]),
            'warehouse_id' => $this->warehouse_id,
            'batch' => $this->batch,
            'expire_date' => $this->expire_date?->toDateString(),
            'allocated_percentage' => $this->allocated_percentage,
            'allocated_amount' => $this->allocated_amount,
            'item_cost_before' => $this->item_cost_before,
            'item_cost_after' => $this->item_cost_after,
            // The per-unit cost the item ends up at once its share of the
            // additional cost is capitalised. Derived here rather than in the
            // page so the API and the view agree on the same number.
            'landed_unit_cost' => (float) $this->quantity > 0
                ? round((float) $this->item_cost_after / (float) $this->quantity, 4)
                : round((float) $this->unit_cost, 4),
        ];
    }
}

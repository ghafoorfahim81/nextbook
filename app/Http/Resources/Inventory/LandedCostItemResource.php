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
            'purchase_item' => PurchaseItemResource::make($this->whenLoaded('purchaseItem')),
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'weight' => $this->weight,
            'volume' => $this->volume,
            'warehouse_id' => $this->warehouse_id,
            'batch' => $this->batch,
            'expire_date' => $this->expire_date?->toDateString(),
            'allocated_percentage' => $this->allocated_percentage,
            'allocated_amount' => $this->allocated_amount,
            'item_cost_before' => $this->item_cost_before,
            'item_cost_after' => $this->item_cost_after,
        ];
    }
}

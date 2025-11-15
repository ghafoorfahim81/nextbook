<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'store' => $this->store?->name,
            'store_id' => $this->store_id,
            'batch' => $this->batch,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'unit_measure_id' => $this->unit_measure_id,
            'measure_unit' => $this->unitMeasure?->name,
            'expiry' => $this->expire_date,
            'date' => $this->date,
            'resource_type' => $this->resource_type,
        ];
    }
}

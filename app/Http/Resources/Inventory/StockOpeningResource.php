<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOpeningResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'quantity' => $this->stock?->quantity,
            'batch' => $this->stock?->batch,
            'expire_date' => $this->stock?->expire_date,
            'store_id' => $this->stock?->store_id,
            'store' => $this->stock?->store,
        ];
    }
}

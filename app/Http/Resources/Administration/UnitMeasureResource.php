<?php

namespace App\Http\Resources\Administration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitMeasureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'unit' => $this->unit,
            'symbol' => $this->symbol,
            'is_active' => $this->is_active,
            'branch_id' => $this->branch_id,
            'quantity_id' => $this->quantity_id,
            'quantity' => $this->quantity,
            'value' => $this->value,
        ];
    }
}

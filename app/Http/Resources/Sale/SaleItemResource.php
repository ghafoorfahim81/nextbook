<?php

namespace App\Http\Resources\Sale;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'sale_number' => $this->sale->number,
            'item_id' => $this->item_id,
            'item_name' => $this->item->name,
            'item_code' => $this->item->code,
            'batch' => $this->batch,
            'expire_date' => $this->expire_date ? $dateConversionService->toDisplay($this->expire_date) : null,
            'quantity' => $this->quantity,
            'unit_measure_id' => $this->unit_measure_id,
            'unit_measure_name' => $this->unitMeasure?->name,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'free' => $this->free,
            'tax' => $this->tax,
            'subtotal' => ($this->quantity * $this->unit_price) - $this->discount + $this->tax,
        ];
    }
}

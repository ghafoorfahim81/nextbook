<?php

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        return [
            'id' => $this->id,
            'purchase_id' => $this->purchase_id,
            'purchase_number' => $this->purchase?->number,
            'item_id' => $this->item_id,
            'item' => $this->whenLoaded('item', function () {
                return [
                    'id' => $this->item?->id,
                    'name' => $this->item?->name,
                    'code' => $this->item?->code,
                    'unit_measure_id' => $this->item?->unit_measure_id,
                    'unitMeasure' => $this->item?->unitMeasure,
                    'purchase_price' => $this->item?->purchase_price,
                    'avg_cost' => $this->item?->avg_cost,
                    'batches' => [],
                    'on_hand' => 0,
                ];
            }),
            'item_name' => $this->item?->name,
            'item_code' => $this->item?->code,
            'batch' => $this->batch,
            'expire_date' => $this->expire_date ? $dateConversionService->toDisplay($this->expire_date) : null,
            'quantity' => $this->quantity,
            'unit_measure_id' => $this->unit_measure_id,
            'unit_measure' => $this->whenLoaded('unitMeasure', fn () => $this->unitMeasure),
            'unit_measure_name' => $this->unitMeasure?->name,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'free' => $this->free,
            'tax' => $this->tax,
            'subtotal' => ($this->quantity * $this->unit_price) - $this->discount + $this->tax,
        ];
    }
}

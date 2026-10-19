<?php

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);

        $quantity = (float) $this->quantity;
        $lineTotal = $quantity * (float) $this->unit_price;

        return [
            'id' => $this->id,
            'purchase_return_id' => $this->purchase_return_id,
            'purchase_item_id' => $this->purchase_item_id,
            'item_id' => $this->item_id,
            'item_name' => $this->item?->name,
            'item_code' => $this->item?->code,
            'variant_id' => $this->variant_id,
            'variant' => $this->whenLoaded('variant', fn () => $this->variant ? [
                'id' => $this->variant->id,
                'display_name' => $this->variant->displayName(),
            ] : null),
            'batch' => $this->batch,
            'expire_date' => $this->expire_date ? $dateConversionService->toDisplay($this->expire_date) : null,
            'quantity' => $this->quantity,
            'unit_measure_id' => $this->unit_measure_id,
            'unit_measure_name' => $this->unitMeasure?->name,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse?->name,
            'unit_price' => $this->unit_price,
            'line_total' => $lineTotal,
        ];
    }
}

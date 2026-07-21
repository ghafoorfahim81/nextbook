<?php

namespace App\Http\Resources\Sale;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);

        $quantity = (float) $this->quantity;
        $unitCost = (float) ($this->net_unit_cost ?? 0.0);
        $lineTotal = $quantity * (float) $this->unit_price;
        $lineCost = $unitCost * $quantity;

        return [
            'id' => $this->id,
            'sale_return_id' => $this->sale_return_id,
            'sale_item_id' => $this->sale_item_id,
            'item_id' => $this->item_id,
            'item_name' => $this->item?->name,
            'item_code' => $this->item?->code,
            'batch' => $this->batch,
            'color' => $this->color,
            'size_id' => $this->size_id,
            'size_name' => $this->size?->name,
            'expire_date' => $this->expire_date ? $dateConversionService->toDisplay($this->expire_date) : null,
            'quantity' => $this->quantity,
            'unit_measure_id' => $this->unit_measure_id,
            'unit_measure_name' => $this->unitMeasure?->name,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse?->name,
            'unit_price' => $this->unit_price,
            'net_unit_cost' => $this->net_unit_cost,
            'line_total' => $lineTotal,
            'line_cost' => $lineCost,
        ];
    }
}

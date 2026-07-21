<?php

namespace App\Http\Resources\Sale;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);

        $quantity = (float) $this->quantity;
        $lineTotal = ($quantity * (float) $this->unit_price) - (float) ($this->discount ?? 0);

        return [
            'id' => $this->id,
            'sale_order_id' => $this->sale_order_id,
            'item_id' => $this->item_id,
            'item_name' => $this->item?->name,
            'item_code' => $this->item?->code,
            'batch' => $this->batch,
            'color' => $this->color,
            'expire_date' => $this->expire_date ? $dateConversionService->toDisplay($this->expire_date) : null,
            'quantity' => $this->quantity,
            'free' => $this->free,
            'unit_measure_id' => $this->unit_measure_id,
            'unit_measure_name' => $this->unitMeasure?->name,
            'size_id' => $this->size_id,
            'size_name' => $this->size?->name,
            'category_id' => $this->category_id,
            'category_name' => $this->category?->name,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'line_total' => $lineTotal,
        ];
    }
}

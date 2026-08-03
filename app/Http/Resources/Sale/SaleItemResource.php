<?php

namespace App\Http\Resources\Sale;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Stock movements are expected to be passed via ->additional(['stockMovements' => $collection])
     * on the parent collection, keyed by item_id. This avoids N+1 queries when rendering
     * a sale with many items.
     */
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);

        // net_unit_cost stores the avg_cost (in the sale's selected unit) captured at sale
        // time, always in the home currency. Prices on this row are in the sale's currency,
        // so the cost has to be restated at the sale's rate before it can be subtracted.
        $rate = (float) ($this->additional['rate'] ?? 1) ?: 1.0;
        $unitCost = (float) ($this->net_unit_cost ?? 0.0) / $rate;
        // Use the sale number passed from SaleResource to avoid lazy-loading $this->sale.
        $saleNumber = $this->additional['saleNumber'] ?? $this->sale_id;

        $quantity   = (float) $this->quantity;
        $subtotal   = ($quantity * (float) $this->unit_price) - (float) ($this->discount ?? 0) + (float) ($this->tax ?? 0);
        $lineCost   = $unitCost * $quantity;
        $lineProfit = $subtotal - $lineCost;

        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'sale_number' => $saleNumber,
            'item_id' => $this->item_id,
            'item' => $this->whenLoaded('item', function () {
                return [
                    'id' => $this->item?->id,
                    'name' => $this->item?->name,
                    'code' => $this->item?->code,
                    'unit_measure_id' => $this->item?->unit_measure_id,
                    'unitMeasure' => $this->item?->unitMeasure,
                    'sale_price' => $this->item?->sale_price,
                    'margin_percentage' => $this->item?->margin_percentage,
                    'rate_a' => $this->item?->rate_a,
                    'rate_b' => $this->item?->rate_b,
                    'rate_c' => $this->item?->rate_c,
                    'batches' => [],
                    'on_hand' => 0,
                ];
            }),
            'item_name' => $this->item->name,
            'item_code' => $this->item->code,
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
            'subtotal' => $subtotal,
            'unit_cost' => $unitCost,
            'line_cost' => $lineCost,
            'line_profit' => $lineProfit,
        ];
    }
}

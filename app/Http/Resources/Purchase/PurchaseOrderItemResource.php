<?php

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);

        $quantity = (float) $this->quantity;
        $lineTotal = ($quantity * (float) $this->unit_price) - (float) ($this->discount ?? 0);

        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'item_id' => $this->item_id,
            'item_name' => $this->item?->name,
            'item_code' => $this->item?->code,
            'item' => $this->whenLoaded('item', function () {
                return [
                    'id' => $this->item?->id,
                    'name' => $this->item?->name,
                    'item_variants' => $this->item?->relationLoaded('variants')
                        ? $this->item->variants->map(fn ($v) => [
                            'id' => $v->id,
                            'sku' => $v->sku,
                            'barcode' => $v->barcode,
                            'display_name' => $v->displayName(),
                            'is_default' => (bool) $v->is_default,
                        ])->values()
                        : [],
                ];
            }),
            'variant_id' => $this->variant_id,
            'variant' => $this->whenLoaded('variant', fn () => $this->variant ? [
                'id' => $this->variant->id,
                'display_name' => $this->variant->displayName(),
            ] : null),
            'batch' => $this->batch,
            'expire_date' => $this->expire_date ? $dateConversionService->toDisplay($this->expire_date) : null,
            'quantity' => $this->quantity,
            'free' => $this->free,
            'unit_measure_id' => $this->unit_measure_id,
            'unit_measure_name' => $this->unitMeasure?->name,
            'category_id' => $this->category_id,
            'category_name' => $this->category?->localized_name,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'line_total' => $lineTotal,
        ];
    }
}

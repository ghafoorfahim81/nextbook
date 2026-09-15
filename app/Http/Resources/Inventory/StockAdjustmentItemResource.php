<?php

namespace App\Http\Resources\Inventory;

use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dateConversionService = app(DateConversionService::class);

        return [
            'id' => $this->id,
            'stock_adjustment_id' => $this->stock_adjustment_id,
            'item_id' => $this->item_id,
            'item' => $this->whenLoaded('item', fn () => [
                'id' => $this->item->id,
                'name' => $this->item->name,
                'code' => $this->item->code,
                'unit_measure_id' => $this->item->unit_measure_id,
                // The item's full variant list, so the edit form can offer a
                // different variant on this line without re-searching for the
                // item first. Requires `items.item.variants` to be eager loaded.
                'variants' => $this->item->relationLoaded('variants')
                    ? $this->item->variants->map(fn ($v) => [
                        'id' => $v->id,
                        'sku' => $v->sku,
                        'barcode' => $v->barcode,
                        'display_name' => $v->displayName(),
                        'is_default' => (bool) $v->is_default,
                    ])->values()
                    : [],
            ]),
            'variant_id' => $this->variant_id,
            'variant' => $this->whenLoaded('variant', fn () => [
                'id' => $this->variant->id,
                'display_name' => $this->variant->displayName(),
                'sku' => $this->variant->sku,
                'barcode' => $this->variant->barcode,
            ]),
            // Blank for a plain product, whose lone default variant is the item
            // itself and needs no second label on the line.
            'variant_label' => $this->whenLoaded('variant', function () {
                $label = (string) $this->variant->displayName();

                return $label === (string) $this->item?->name ? '' : $label;
            }),
            'unit_measure_id' => $this->unit_measure_id,
            'unit_measure' => $this->whenLoaded('unitMeasure', fn () => [
                'id' => $this->unitMeasure->id,
                'name' => $this->unitMeasure->name,
                'unit' => $this->unitMeasure->unit,
            ]),
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'total_cost' => (float) $this->quantity * (float) ($this->unit_cost ?? 0),
            'batch' => $this->batch,
            'expire_date' => $this->expire_date ? $dateConversionService->toDisplay($this->expire_date) : null,
            'category_id' => $this->category_id,
            'branch_id' => $this->branch_id,
        ];
    }
}

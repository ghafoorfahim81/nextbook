<?php

namespace App\Http\Resources\ItemTransfer;

use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemTransferItemResource extends JsonResource
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
            'item_transfer_id' => $this->item_transfer_id,
            'item_id' => $this->item_id,
            'item' => $this->whenLoaded('item', fn() => [
                'id' => $this->item->id,
                'name' => $this->item->name,
                'code' => $this->item->code,
                'item_variants' => $this->item->relationLoaded('variants')
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
            'variant' => $this->whenLoaded('variant', fn () => $this->variant ? [
                'id' => $this->variant->id,
                'display_name' => $this->variant->displayName(),
            ] : null),
            'batch' => $this->batch,
            'expire_date' => $this->expire_date ? $dateConversionService->toDisplay($this->expire_date) : null,
            'quantity' => $this->quantity,
            'measure_id' => $this->measure_id,
            'unit_measure' => $this->whenLoaded('unitMeasure', fn() => [
                'id' => $this->unitMeasure->id,
                'name' => $this->unitMeasure->name,
            ]),
            'unit_price' => $this->unit_price,
            'branch_id' => $this->branch_id,
        ];
    }
}

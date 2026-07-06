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
            ]),
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
            'size_id' => $this->size_id,
            'category_id' => $this->category_id,
            'branch_id' => $this->branch_id,
        ];
    }
}

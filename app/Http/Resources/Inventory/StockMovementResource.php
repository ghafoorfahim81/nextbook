<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Administration\WarehouseResource;
use App\Http\Resources\Administration\UnitMeasureResource;
use App\Services\DecimalNumberFormat;
class StockMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'warehouse_name' => $this->warehouse?->name ?? null,
            'warehouse_id' => $this->warehouse_id,
            'batch' => $this->batch,
            'source' => $this->source?->getLabel() ?? null,
            'status' => $this->status,
            'movement_type' => $this->movement_type?->getLabel() ?? null,
            'quantity' => (new DecimalNumberFormat())->removeTrailingDecimalZeros($this->quantity),
            'unit_cost' => $this->unit_cost,
            'unit_price' => (new DecimalNumberFormat())->removeTrailingDecimalZeros($this->unit_cost),
            'unit_measure' => UnitMeasureResource::make($this->whenLoaded('unitMeasure')),
            'unit_measure_name' => $this->unitMeasure?->name ?? null,
            'expire_date' => $this->expire_date ? $dateConversionService->toDisplay($this->expire_date) : null,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'bill_number' => $this->reference?->number ?? null,
            'ledger_name' => $this->ledgerName(),
        ];
    }
}

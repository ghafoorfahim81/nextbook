<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
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
            'store' => $this->store?->name,
            'store_id' => $this->store_id,
            'batch' => $this->batch,
            'bill_number' => $this->source?->number ?? null,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'unit_measure_id' => $this->unit_measure_id,
            'measure_unit' => $this->unitMeasure?->name,
            'expiry' => $this->expire_date ? $dateConversionService->toDisplay($this->expire_date) : null,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'ledger_name' => $this->ledgerName(),
            'source_type' => $this->getSourceTypeName(),
        ];
    }

    private function getSourceTypeName(): string
    {
        if (!$this->source_type) {
            return 'Opening Stock';
        }

        return match($this->source_type) {
            'App\Models\Purchase\Purchase' => 'Purchase',
            'App\Models\Sale\Sale' => 'Sale',
            default => class_basename($this->source_type),
        };
    }
}

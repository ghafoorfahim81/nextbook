<?php

namespace App\Http\Resources\Purchase;

use App\Enums\PurchaseOrderStatus;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
            'date' => app(DateConversionService::class)->toDisplay($this->date),
            'delivery_date' => $this->delivery_date ? app(DateConversionService::class)->toDisplay($this->delivery_date) : null,
            'amount' => (float) ($this->items_gross_total ?? 0),
            'status' => $this->status,
            'status_label' => PurchaseOrderStatus::tryFrom((string) $this->status)?->getLabel() ?? $this->status,
        ];
    }
}

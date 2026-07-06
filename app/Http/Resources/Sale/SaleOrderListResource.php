<?php

namespace App\Http\Resources\Sale;

use App\Enums\SaleOrderStatus;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleOrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'date' => app(DateConversionService::class)->toDisplay($this->date),
            'delivery_date' => $this->delivery_date ? app(DateConversionService::class)->toDisplay($this->delivery_date) : null,
            'amount' => (float) ($this->items_gross_total ?? 0),
            'status' => $this->status,
            'status_label' => SaleOrderStatus::tryFrom((string) $this->status)?->getLabel() ?? $this->status,
        ];
    }
}

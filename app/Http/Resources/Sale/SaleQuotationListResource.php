<?php

namespace App\Http\Resources\Sale;

use App\Enums\SaleQuotationStatus;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleQuotationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'date' => app(DateConversionService::class)->toDisplay($this->date),
            'valid_until' => $this->valid_until ? app(DateConversionService::class)->toDisplay($this->valid_until) : null,
            'amount' => (float) ($this->items_gross_total ?? 0),
            'status' => $this->status,
            'status_label' => SaleQuotationStatus::tryFrom((string) $this->status)?->getLabel() ?? $this->status,
        ];
    }
}

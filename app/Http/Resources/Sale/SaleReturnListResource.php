<?php

namespace App\Http\Resources\Sale;

use App\Enums\SaleReturnReason;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleReturnListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'sale_id' => $this->sale_id,
            'sale_number' => $this->sale?->number,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'date' => app(DateConversionService::class)->toDisplay($this->date),
            'reason' => $this->reason instanceof SaleReturnReason ? $this->reason->value : $this->reason,
            'reason_label' => $this->reason instanceof SaleReturnReason
                ? $this->reason->getLabel()
                : (SaleReturnReason::tryFrom((string) $this->reason)?->getLabel() ?? $this->reason),
            'amount' => (float) ($this->items_gross_total ?? 0),
            'status' => $this->status,
        ];
    }
}

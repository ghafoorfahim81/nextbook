<?php

namespace App\Http\Resources\Purchase;

use App\Enums\PurchaseQuotationStatus;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseQuotationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
            'date' => app(DateConversionService::class)->toDisplay($this->date),
            'valid_until' => $this->valid_until ? app(DateConversionService::class)->toDisplay($this->valid_until) : null,
            'amount' => $this->netAmount(),
            'status' => $this->status,
            'status_label' => PurchaseQuotationStatus::tryFrom((string) $this->status)?->getLabel() ?? $this->status,
        ];
    }

    /** Value of goods less line discounts and the document discount: what the document comes to. */
    private function netAmount(): float
    {
        $gross = (float) ($this->items_gross_total ?? 0);
        $documentDiscount = $this->discount_type === 'percentage'
            ? $gross * ((float) ($this->discount ?? 0) / 100)
            : (float) ($this->discount ?? 0);

        return $gross - (float) ($this->items_discount_total ?? 0) - $documentDiscount;
    }
}

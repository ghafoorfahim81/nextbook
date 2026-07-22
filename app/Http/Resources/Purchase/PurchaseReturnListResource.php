<?php

namespace App\Http\Resources\Purchase;

use App\Enums\PurchaseReturnReason;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'purchase_id' => $this->purchase_id,
            'purchase_number' => $this->purchase?->number,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
            'date' => app(DateConversionService::class)->toDisplay($this->date),
            'reason' => $this->reason instanceof PurchaseReturnReason ? $this->reason->value : $this->reason,
            'reason_label' => $this->reason instanceof PurchaseReturnReason
                ? $this->reason->getLabel()
                : (PurchaseReturnReason::tryFrom((string) $this->reason)?->getLabel() ?? $this->reason),
            'amount' => (float) ($this->items_gross_total ?? 0),
            'status' => $this->status,
        ];
    }
}

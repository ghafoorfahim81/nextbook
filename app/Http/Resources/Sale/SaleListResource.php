<?php

namespace App\Http\Resources\Sale;

use App\Enums\PaymentStatus;
use App\Enums\SalePurchaseType;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $grossTotal = (float) ($this->items_gross_total ?? 0);
        $itemDiscountTotal = (float) ($this->items_discount_total ?? 0);
        $itemTaxTotal = (float) ($this->items_tax_total ?? 0);
        $billDiscount = $this->discount_type === 'percentage'
            ? $grossTotal * ((float) $this->discount / 100)
            : (float) ($this->discount ?? 0);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'payment_status' => $this->payment_status instanceof PaymentStatus
                ? $this->payment_status->value
                : $this->payment_status,
            'payment_status_label' => $this->payment_status instanceof PaymentStatus
                ? $this->payment_status->getLabel()
                : (PaymentStatus::tryFrom((string) $this->payment_status)?->getLabel() ?? $this->payment_status),
            'amount' => $grossTotal - $itemDiscountTotal - $billDiscount + $itemTaxTotal,
            'date' => app(DateConversionService::class)->toDisplay($this->date),
            'type' => $this->type instanceof SalePurchaseType
                ? $this->type->getLabel()
                : (SalePurchaseType::tryFrom((string) $this->type)?->getLabel() ?? $this->type),
            'sale_purchase_type_id' => $this->type instanceof SalePurchaseType
                ? $this->type->value
                : $this->type,
            'status' => $this->status,
        ];
    }
}

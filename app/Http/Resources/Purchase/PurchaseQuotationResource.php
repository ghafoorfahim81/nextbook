<?php

namespace App\Http\Resources\Purchase;

use App\Enums\PurchaseQuotationStatus;
use App\Http\Resources\UserManagement\UserSimpleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseQuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $dateConversionService->toDisplay($this->date),
            'valid_until' => $this->valid_until ? $dateConversionService->toDisplay($this->valid_until) : null,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier'),
            'supplier_name' => $this->whenLoaded('supplier', fn () => $this->supplier?->name),
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency'),
            'rate' => $this->rate,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse'),
            'discount' => $this->discount,
            'discount_type' => $this->discount_type,
            'status' => $this->status,
            'status_label' => PurchaseQuotationStatus::tryFrom((string) $this->status)?->getLabel() ?? $this->status,
            'note' => $this->note,
            'quotation_total' => $this->relationLoaded('items') ? $this->quotationTotal() : null,
            'items' => $this->whenLoaded('items', fn () => PurchaseQuotationItemResource::collection($this->items)),
            'item_list' => $this->whenLoaded('items', $this->items->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'item_name' => $item->item?->name,
                    'quantity' => $item->quantity,
                    'free' => $item->free,
                    'unit_price' => $item->unit_price,
                    'unit_measure_id' => $item->unit_measure_id,
                    'batch' => $item->batch,
                    'color' => $item->color,
                    'expire_date' => $item->expire_date,
                    'size_id' => $item->size_id,
                    'category_id' => $item->category_id,
                    'discount' => $item->discount,
                ];
            })),
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
        ];
    }
}

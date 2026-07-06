<?php

namespace App\Http\Resources\Sale;

use App\Enums\SaleOrderStatus;
use App\Http\Resources\UserManagement\UserSimpleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $dateConversionService->toDisplay($this->date),
            'delivery_date' => $this->delivery_date ? $dateConversionService->toDisplay($this->delivery_date) : null,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer'),
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency'),
            'rate' => $this->rate,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse'),
            'discount' => $this->discount,
            'discount_type' => $this->discount_type,
            'status' => $this->status,
            'status_label' => SaleOrderStatus::tryFrom((string) $this->status)?->getLabel() ?? $this->status,
            'note' => $this->note,
            'order_total' => $this->relationLoaded('items') ? $this->orderTotal() : null,
            'sale_id' => $this->whenLoaded('sale', fn () => $this->sale?->id),
            'sale_number' => $this->whenLoaded('sale', fn () => $this->sale?->number),
            'items' => $this->whenLoaded('items', fn () => SaleOrderItemResource::collection($this->items)),
            'item_list' => $this->whenLoaded('items', $this->items->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'item_name' => $item->item?->name,
                    'quantity' => $item->quantity,
                    'free' => $item->free,
                    'unit_price' => $item->unit_price,
                    'unit_measure_id' => $item->unit_measure_id,
                    'batch' => $item->batch,
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

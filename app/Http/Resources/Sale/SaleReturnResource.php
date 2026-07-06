<?php

namespace App\Http\Resources\Sale;

use App\Enums\SaleReturnReason;
use App\Http\Resources\Transaction\TransactionResource;
use App\Http\Resources\UserManagement\UserSimpleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        $items = $this->relationLoaded('items') ? $this->items : collect();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'sale_id' => $this->sale_id,
            'sale_number' => $this->whenLoaded('sale', fn () => $this->sale?->number),
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer'),
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'date' => $dateConversionService->toDisplay($this->date),
            'reason' => $this->reason instanceof SaleReturnReason ? $this->reason->value : $this->reason,
            'reason_label' => $this->reason instanceof SaleReturnReason
                ? $this->reason->getLabel()
                : (SaleReturnReason::tryFrom((string) $this->reason)?->getLabel() ?? $this->reason),
            'description' => $this->description,
            'status' => $this->status,
            'amount' => $items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price),
            'transaction' => new TransactionResource($this->whenLoaded('transaction', $this->transaction)),
            'items' => $this->whenLoaded('items', fn () => SaleReturnItemResource::collection($this->items)),
            'item_list' => $this->whenLoaded('items', $this->items->map(function ($item) {
                return [
                    'sale_item_id' => $item->sale_item_id,
                    'item_id' => $item->item_id,
                    'item_name' => $item->item?->name,
                    'batch' => $item->batch,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'net_unit_cost' => $item->net_unit_cost,
                    'unit_measure_id' => $item->unit_measure_id,
                    'warehouse_id' => $item->warehouse_id,
                ];
            })),
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
        ];
    }
}

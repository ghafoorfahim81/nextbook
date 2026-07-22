<?php

namespace App\Http\Resources\Purchase;

use App\Enums\PurchaseReturnReason;
use App\Http\Resources\Transaction\TransactionResource;
use App\Http\Resources\UserManagement\UserSimpleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        $items = $this->relationLoaded('items') ? $this->items : collect();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'purchase_id' => $this->purchase_id,
            'purchase_number' => $this->whenLoaded('purchase', fn () => $this->purchase?->number),
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier'),
            'supplier_name' => $this->whenLoaded('supplier', fn () => $this->supplier?->name),
            'date' => $dateConversionService->toDisplay($this->date),
            'reason' => $this->reason instanceof PurchaseReturnReason ? $this->reason->value : $this->reason,
            'reason_label' => $this->reason instanceof PurchaseReturnReason
                ? $this->reason->getLabel()
                : (PurchaseReturnReason::tryFrom((string) $this->reason)?->getLabel() ?? $this->reason),
            'description' => $this->description,
            'status' => $this->status,
            'amount' => $items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price),
            'transaction' => new TransactionResource($this->whenLoaded('transaction', $this->transaction)),
            'items' => $this->whenLoaded('items', fn () => PurchaseReturnItemResource::collection($this->items)),
            'item_list' => $this->whenLoaded('items', $this->items->map(function ($item) {
                return [
                    'purchase_item_id' => $item->purchase_item_id,
                    'item_id' => $item->item_id,
                    'item_name' => $item->item?->name,
                    'batch' => $item->batch,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'unit_measure_id' => $item->unit_measure_id,
                    'warehouse_id' => $item->warehouse_id,
                ];
            })),
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
        ];
    }
}

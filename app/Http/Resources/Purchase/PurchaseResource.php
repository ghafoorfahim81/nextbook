<?php

namespace App\Http\Resources\Purchase;

use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */

    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier'),
            'supplier_name' => $this->supplier?->name,
            'date' => $dateConversionService->toDisplay($this->date),
            'transaction_id' => $this->transaction_id,
            'amount' => $this->transaction?->amount,
            'discount' => $this->discount,
            'discount_type' => $this->discount_type,
            'type' => $this->type,
            'sale_purchase_type_id' => $this->type,
            'description' => $this->description,
            'status' => $this->status, 
            'transaction_total' => $this->transaction?->amount,
            'transaction' => new TransactionResource($this->whenLoaded('transaction', $this->transaction)),
            'store' => $this->whenLoaded('stock', $this->stock?->store),
            'currency_id' => $this->transaction?->currency_id,
            'rate' => $this->transaction?->rate,
            'items' => $this->whenLoaded('items', PurchaseItemResource::collection($this->items)),
            'item_list' => $this->whenLoaded('items', $this->items->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'free' => $item->free,
                    'batch' => $item->batch,
                    'discount' => $item->discount,
                    'tax' => $item->tax,
                    'unit_measure_id' => $item->unit_measure_id, 
                ];
            })),
        ];
    }
}

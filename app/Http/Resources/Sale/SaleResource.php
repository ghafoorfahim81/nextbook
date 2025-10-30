<?php

namespace App\Http\Resources\Sale;

use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
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
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer'),
            'customer_name' => $this->customer?->name,
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
            'store' => $this->stockOuts[0]?->store,
            'store_id' => $this->stockOuts[0]?->store?->id,
            'currency_id' => $this->transaction?->currency_id,
            'rate' => $this->transaction?->rate,
            'items' => $this->whenLoaded('items', SaleItemResource::collection($this->items)),
            'item_list' => $this->whenLoaded('items', $this->items->map(function ($item) use ($dateConversionService) {
                return [
                    'item_id' => $item->item_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'expire_date' => $item->expire_date ? $dateConversionService->toDisplay($item->expire_date) : null,
                    'free' => $item->free,
                    'batch' => $item->batch,
                    'store_id' => $item->store_id,
                    'store' => $item->store,
                    'item_discount' => $item->discount,
                    'tax' => $item->tax,
                    'unit_measure_id' => $item->unit_measure_id,
                ];
            })),
        ];
    }
}

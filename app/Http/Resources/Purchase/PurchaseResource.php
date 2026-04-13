<?php

namespace App\Http\Resources\Purchase;

use App\Enums\SalePurchaseType;
use App\Enums\PaymentStatus;
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
            'due_date' => $dateConversionService->toDisplay($this->due_date),
            'amount' => $this->items->sum(function ($item) {
                $row_total = floatval($item->quantity) * floatval($item->unit_price);
                $item_discount = floatval($item->discount ?? 0);

                if ($this->discount_type === 'percentage') {
                    $sale_discount = $row_total * (floatval($this->discount) / 100);
                } else {
                    $sale_discount = floatval($this->discount ?? 0);
                }

                return $row_total - $item_discount - $sale_discount;
            }),
            'discount' => $this->discount,
            'discount_type' => $this->discount_type,
            'type' => ($this->type instanceof SalePurchaseType)
                ? $this->type->getLabel()
                : (SalePurchaseType::tryFrom((string) $this->type)?->getLabel() ?? $this->type),
            'sale_purchase_type_id' => ($this->type instanceof SalePurchaseType)
                ? $this->type->value
                : $this->type,
            'payable_amount' => $this->transaction?->lines->sum(function ($line) {
                return $line->ledger_id !== null ? $line->credit : 0;
            }),
            'raw_type' => $this->type,
            'bank_account_id' => $this->bank_account_id,
            'description' => $this->description,
            'status' => $this->status,
            'payment_status' => $this->payment_status instanceof PaymentStatus
                ? $this->payment_status->value
                : $this->payment_status,
            'payment_status_label' => $this->payment_status instanceof PaymentStatus
                ? $this->payment_status->getLabel()
                : (PaymentStatus::tryFrom((string) $this->payment_status)?->getLabel() ?? $this->payment_status),
            'transaction_total' => $this->transaction?->amount,
            'transaction' => new TransactionResource($this->whenLoaded('transaction', $this->transaction)),
            'warehouse' => $this->warehouse(),
            'warehouse_id' => $this->warehouse()?->id,
            'currency_id' => $this->transaction?->currency_id,
            'rate' => $this->transaction?->rate,
            'items' => $this->whenLoaded('items', PurchaseItemResource::collection($this->items)),
            'item_list' => $this->whenLoaded('items', $this->items->map(function ($item) use ($dateConversionService) {
                return [
                    'item_id' => $item->item_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'expire_date' => $item->expire_date ? $dateConversionService->toDisplay($item->expire_date) : null,
                    'free' => $item->free,
                    'batch' => $item->batch,
                    'warehouse_id' => $item->warehouse_id,
                    'warehouse' => $item->warehouse,
                    'item_discount' => $item->discount,
                    'tax' => $item->tax,
                    'unit_measure_id' => $item->unit_measure_id,
                ];
            })),
        ];
    }
}

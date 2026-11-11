<?php

namespace App\Http\Resources\Purchase;

use App\Enums\TransactionStatus;
use App\Enums\SalePurchaseType;
use App\Enums\PaymentStatus;
use App\Http\Resources\Transaction\TransactionResource;
use App\Http\Resources\UserManagement\UserSimpleResource;
use App\Http\Resources\AttachmentResource;
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
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order_number' => $this->whenLoaded('purchaseOrder', fn () => $this->purchaseOrder?->number),
            'date' => $dateConversionService->toDisplay($this->date),
            'due_date' => $dateConversionService->toDisplay($this->due_date),
            'updated_at' => $dateConversionService->toDisplay($this->updated_at?->toDateString()),
            // The same total the ledger was posted with (PurchaseController
            // works it out with this formula). A fixed bill discount used to
            // come off once per line here.
            'amount' => $this->resource->purchaseTotal(),
            'discount' => $this->discount,
            'discount_type' => $this->discount_type,
            'type' => ($this->type instanceof SalePurchaseType)
                ? $this->type->getLabel()
                : (SalePurchaseType::tryFrom((string) $this->type)?->getLabel() ?? $this->type),
            'sale_purchase_type_id' => ($this->type instanceof SalePurchaseType)
                ? $this->type->value
                : $this->type,
            'payable_amount' => $this->payableAmount(),
            'raw_type' => $this->type,
            'bank_account_id' => $this->bank_account_id,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => TransactionStatus::tryFrom((string) ($this->status))?->getLabel()
                ?? (string) ($this->status),
            'payment_status' => $this->payment_status instanceof PaymentStatus
                ? $this->payment_status->value
                : $this->payment_status,
            'payment_status_label' => $this->payment_status instanceof PaymentStatus
                ? $this->payment_status->getLabel()
                : (PaymentStatus::tryFrom((string) $this->payment_status)?->getLabel() ?? $this->payment_status),
            'transaction_total' => $this->resource->purchaseTotal(),
            'transaction' => new TransactionResource($this->whenLoaded('transaction', $this->transaction)),
            'warehouse' => $this->warehouse(),
            'warehouse_id' => $this->warehouse()?->id,
            'currency_id' => $this->transaction?->currency_id,
            'currency_code' => $this->transaction?->currency?->code,
            'rate' => $this->transaction?->rate,
            // How the bill was paid, as the form sent it. A draft has no ledger
            // lines, so the edit form reads them back from here.
            'payment' => data_get($this->transaction?->posting_payload, 'purchase.payment'),
            // Cash/bank account the bill was paid from — details page only.
            'payment_account' => $request->routeIs('purchases.show')
                ? \App\Support\Documents\PaymentAccount::for($this->transaction, $this->bank_account_id, 'purchase')
                : null,
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
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'returns' => $this->whenLoaded('returns', fn () => $this->returns->map(function ($return) {
                return [
                    'id' => $return->id,
                    'number' => $return->number,
                    'date' => $return->date?->toDateString(),
                    'status' => $return->status,
                    'quantity' => $return->items->sum(fn ($item) => (float) $item->quantity),
                    'amount' => $return->items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price),
                ];
            })),
        ];
    }

    /** What is owed to the supplier on this bill; read from the draft's payload until it is posted. */
    private function payableAmount(): float
    {
        $lines = $this->transaction?->lines ?? collect();

        if ($lines->isEmpty() && $this->transaction) {
            $lines = collect((array) data_get($this->transaction->posting_payload, 'lines', []))->map(fn ($line) => (object) $line);
        }

        return money_round($lines->sum(fn ($line) => ($line->ledger_id ?? null) !== null ? (float) ($line->credit ?? 0) : 0));
    }
}

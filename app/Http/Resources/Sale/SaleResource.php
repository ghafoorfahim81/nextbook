<?php

namespace App\Http\Resources\Sale;

use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\SalePurchaseType;
use App\Enums\PaymentStatus;

class SaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */

    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        $transaction = $this->relationLoaded('transaction') ? $this->transaction : null;
        $transactionRate = (float) ($transaction?->rate ?? 1);
        $transactionLines = $transaction?->relationLoaded('lines')
            ? $transaction->lines
            : collect();
        $customerStatement = $this->relationLoaded('customer') && $this->customer
            ? $this->customer->statement
            : null;
        $ledgerEffect = $transactionLines
            ->where('ledger_id', $this->customer_id)
            ->sum(fn ($line) => ((float) $line->debit - (float) $line->credit) * $transactionRate);
        $remainingAmount = abs((float) $ledgerEffect);
        $oldNetBalance = (float) data_get($customerStatement, 'net_balance', 0) - (float) $ledgerEffect;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer'),
            'customer_name' => $this->customer?->name,
            'date' => $dateConversionService->toDisplay($this->date),
            'due_date' => $dateConversionService->toDisplay($this->due_date),
            'transaction_id' => $this->transaction_id,
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
            'raw_type' => $this->type,
            'receivable_amount' => $remainingAmount,
            'remaining_amount' => $remainingAmount,
            'old_balance' => abs($oldNetBalance),
            'old_balance_nature' => $oldNetBalance >= 0 ? 'dr' : 'cr',
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
            'items' => $this->whenLoaded('items', SaleItemResource::collection($this->items)),
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

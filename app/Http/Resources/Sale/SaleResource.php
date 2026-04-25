<?php

namespace App\Http\Resources\Sale;

use App\Http\Resources\Transaction\TransactionResource;
use App\Http\Resources\UserManagement\UserSimpleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\SalePurchaseType;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\Inventory\StockMovement;

class SaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */

    public function toArray(Request $request): array
    {
        $isListing = $request->routeIs('sales.index');
        $dateConversionService = app(\App\Services\DateConversionService::class);
        $transaction = $this->relationLoaded('transaction') ? $this->transaction : null;
        $transactionRate = (float) ($transaction?->rate ?? 1);
        $transactionLines = $transaction?->relationLoaded('lines')
            ? $transaction->lines
            : collect();
        $customerStatement = !$isListing && $this->relationLoaded('customer') && $this->customer
            ? $this->customer->statement
            : null;
        $ledgerEffect = $transactionLines
            ->where('ledger_id', $this->customer_id)
            ->sum(fn ($line) => ((float) $line->debit - (float) $line->credit) * $transactionRate);
        $remainingAmount = abs((float) $ledgerEffect);
        $oldNetBalance = (float) data_get($customerStatement, 'net_balance', 0) - (float) $ledgerEffect;
        $items = $this->relationLoaded('items') ? $this->items : collect();
        // `transactions` has no `amount` column — always compute from items when loaded.
        $saleAmount = $items->sum(function ($item) {
            $row_total = (float) $item->quantity * (float) $item->unit_price;
            $item_discount = (float) ($item->discount ?? 0);
            $sale_discount = $this->discount_type === 'percentage'
                ? $row_total * ((float) $this->discount / 100)
                : (float) ($this->discount ?? 0);

            return $row_total - $item_discount - $sale_discount;
        });
        $warehouse = $this->relationLoaded('items') ? $this->warehouse() : null;

        // Resolve stock movements: use pre-loaded collection if passed via ->additional(),
        // otherwise fetch once from DB (e.g. for the show/print endpoints).
        $stockMovements = $this->additional['stockMovements'] ?? null;
        if ($stockMovements === null && !$isListing && $this->relationLoaded('items')) {
            $stockMovements = StockMovement::where('reference_id', $this->id)
                ->where('reference_type', \App\Models\Sale\Sale::class)
                ->where('movement_type', StockMovementType::OUT)
                ->get(['item_id', 'unit_cost', 'quantity'])
                ->keyBy('item_id');
        }

        // Compute total cost and profit from the already-resolved movements (no extra query).
        $totalCostValue = null;
        $totalProfitValue = null;
        if (!$isListing && $stockMovements !== null) {
            $totalCostValue = $stockMovements->sum(fn ($m) => (float) $m->unit_cost * (float) $m->quantity);

            $totalRevenue = $items->sum(function ($item) {
                return (float) $item->quantity * (float) $item->unit_price
                    - (float) ($item->discount ?? 0)
                    + (float) ($item->tax ?? 0);
            });
            $saleLevelDiscount = $this->discount_type === 'percentage'
                ? $items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price)
                    * ((float) $this->discount / 100)
                : (float) ($this->discount ?? 0);

            $totalProfitValue = ($totalRevenue - $saleLevelDiscount) - $totalCostValue;
        }

        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer'),
            'customer_name' => $this->customer?->name,
            'date' => $dateConversionService->toDisplay($this->date),
            'due_date' => $dateConversionService->toDisplay($this->due_date),
            'updated_at' => $dateConversionService->toDisplay($this->updated_at?->toDateString()),
            'transaction_id' => $this->transaction_id,
            'amount' => $saleAmount,
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
            'transaction_total' => $saleAmount,
            'transaction' => new TransactionResource($this->whenLoaded('transaction', $this->transaction)),
            'warehouse' => $warehouse,
            'warehouse_id' => $warehouse?->id,
            'currency_id' => $this->transaction?->currency_id,
            'rate' => $this->transaction?->rate,
            'items' => $this->whenLoaded('items', fn () =>
                SaleItemResource::collection($this->items)->additional([
                    'stockMovements' => $stockMovements ?? collect(),
                    'saleNumber'     => $this->number,
                ])
            ),
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
            'total_cost' => $totalCostValue,
            'total_profit' => $totalProfitValue,
        ];
    }
}

<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment\Payment;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchasePayment;
use App\Models\Receipt\Receipt;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleReceive;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillAllocationService
{
    public function syncReceiptAllocations(Receipt $receipt, float $receiptAmount, array $allocations): array
    {
        $receipt->loadMissing('saleReceives');

        $affectedSaleIds = $receipt->saleReceives->pluck('sale_id')->all();

        $receipt->saleReceives()->forceDelete();

        $normalized = $this->normalizeAllocations($allocations, 'sale_id');
        $billRecords = $this->loadSalesForAllocations($normalized);
        $paymentAmount = $receiptAmount;
        $allocatedTotal = 0.0;

        foreach ($normalized as $index => $allocation) {
            $sale = $billRecords->get($allocation['bill_id']);
            if (!$sale) {
                throw ValidationException::withMessages([
                    "allocations.$index.bill_id" => __('The selected sale bill is invalid.'),
                ]);
            }

            $remaining = $this->saleRemainingAmount($sale);
            if ($allocation['amount'] > $remaining + 0.00001) {
                throw ValidationException::withMessages([
                    "allocations.$index.amount" => __('The allocated amount cannot exceed the bill remaining amount.'),
                ]);
            }

            $allocatedTotal += $allocation['amount'];
            $affectedSaleIds[] = $sale->id;

            SaleReceive::create([
                'sale_id' => $sale->id,
                'receipt_id' => $receipt->id,
                'amount' => $allocation['amount'],
                'branch_id' => $receipt->branch_id,
                'created_by' => $receipt->created_by,
                'updated_by' => $receipt->updated_by,
            ]);
        }

        if ($allocatedTotal - $paymentAmount > 0.00001) {
            throw ValidationException::withMessages([
                'allocations' => __('The allocated amount cannot exceed the receipt amount.'),
            ]);
        }

        $this->recalculateSales(array_values(array_unique($affectedSaleIds)));

        return array_values(array_unique($affectedSaleIds));
    }

    public function syncPaymentAllocations(Payment $payment, float $paymentAmount, array $allocations): array
    {
        $payment->loadMissing('purchasePayments');

        $affectedPurchaseIds = $payment->purchasePayments->pluck('purchase_id')->all();

        $payment->purchasePayments()->forceDelete();

        $normalized = $this->normalizeAllocations($allocations, 'purchase_id');
        $billRecords = $this->loadPurchasesForAllocations($normalized);
        $allocatedTotal = 0.0;

        foreach ($normalized as $index => $allocation) {
            $purchase = $billRecords->get($allocation['bill_id']);
            if (!$purchase) {
                throw ValidationException::withMessages([
                    "allocations.$index.bill_id" => __('The selected purchase bill is invalid.'),
                ]);
            }

            $remaining = $this->purchaseRemainingAmount($purchase);
            if ($allocation['amount'] > $remaining + 0.00001) {
                throw ValidationException::withMessages([
                    "allocations.$index.amount" => __('The allocated amount cannot exceed the bill remaining amount.'),
                ]);
            }

            $allocatedTotal += $allocation['amount'];
            $affectedPurchaseIds[] = $purchase->id;

            PurchasePayment::create([
                'purchase_id' => $purchase->id,
                'payment_id' => $payment->id,
                'amount' => $allocation['amount'],
                'branch_id' => $payment->branch_id,
                'created_by' => $payment->created_by,
                'updated_by' => $payment->updated_by,
            ]);
        }

        if ($allocatedTotal - $paymentAmount > 0.00001) {
            throw ValidationException::withMessages([
                'allocations' => __('The allocated amount cannot exceed the payment amount.'),
            ]);
        }

        $this->recalculatePurchases(array_values(array_unique($affectedPurchaseIds)));

        return array_values(array_unique($affectedPurchaseIds));
    }

    public function recalculateSalePaymentStatuses(array $saleIds): void
    {
        $this->recalculateSales($saleIds);
    }

    public function recalculatePurchasePaymentStatuses(array $purchaseIds): void
    {
        $this->recalculatePurchases($purchaseIds);
    }

    public function openSalesForCustomer(string $customerId, ?string $excludeReceiptId = null): Collection
    {
        return Sale::query()
            ->with([
                'items',
                'transaction.lines.account',
                'saleReceives' => fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->when($excludeReceiptId, fn ($query) => $query->where('receipt_id', '!=', $excludeReceiptId)),
            ])
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->orderBy('date')
            ->orderBy('number')
            ->get()
            ->map(function (Sale $sale): array {
                $billTotal = $this->saleBillTotal($sale);
                $remaining = $this->saleRemainingAmount($sale);

                return [
                    'id' => $sale->id,
                    'number' => $sale->number,
                    'date' => $sale->date?->toDateString(),
                    'due_date' => $sale->due_date?->toDateString(),
                    'bill_total' => $billTotal,
                    'allocated_amount' => $this->saleAllocatedAmount($sale),
                    'remaining_amount' => $remaining,
                    'payment_status' => $sale->payment_status?->value ?? $sale->payment_status,
                ];
            })
            ->filter(fn (array $sale) => $sale['remaining_amount'] > 0)
            ->values();
    }

    public function openPurchasesForSupplier(string $supplierId, ?string $excludePaymentId = null): Collection
    {
        return Purchase::query()
            ->with([
                'items',
                'transaction.lines.account',
                'purchasePayments' => fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->when($excludePaymentId, fn ($query) => $query->where('payment_id', '!=', $excludePaymentId)),
            ])
            ->where('supplier_id', $supplierId)
            ->whereNull('deleted_at')
            ->orderBy('date')
            ->orderBy('number')
            ->get()
            ->map(function (Purchase $purchase): array {
                $billTotal = $this->purchaseBillTotal($purchase);
                $remaining = $this->purchaseRemainingAmount($purchase);

                return [
                    'id' => $purchase->id,
                    'number' => $purchase->number,
                    'date' => $purchase->date?->toDateString(),
                    'due_date' => $purchase->due_date?->toDateString(),
                    'bill_total' => $billTotal,
                    'allocated_amount' => $this->purchaseAllocatedAmount($purchase),
                    'remaining_amount' => $remaining,
                    'payment_status' => $purchase->payment_status?->value ?? $purchase->payment_status,
                ];
            })
            ->filter(fn (array $purchase) => $purchase['remaining_amount'] > 0)
            ->values();
    }

    private function recalculateSales(array $saleIds): void
    {
        if (empty($saleIds)) {
            return;
        }

        Sale::query()
            ->with([
                'items',
                'transaction.lines.account',
                'saleReceives' => fn ($query) => $query->whereNull('deleted_at'),
            ])
            ->whereIn('id', $saleIds)
            ->get()
            ->each(function (Sale $sale): void {
                $sale->update([
                    'payment_status' => $this->resolvePaymentStatus(
                        billTotal: $this->saleBillTotal($sale),
                        outstandingBeforeAllocations: $this->saleOutstandingAmount($sale),
                        allocatedTotal: $this->saleAllocatedAmount($sale),
                    )->value,
                ]);
            });
    }

    private function recalculatePurchases(array $purchaseIds): void
    {
        if (empty($purchaseIds)) {
            return;
        }

        Purchase::query()
            ->with([
                'items',
                'transaction.lines.account',
                'purchasePayments' => fn ($query) => $query->whereNull('deleted_at'),
            ])
            ->whereIn('id', $purchaseIds)
            ->get()
            ->each(function (Purchase $purchase): void {
                $purchase->update([
                    'payment_status' => $this->resolvePaymentStatus(
                        billTotal: $this->purchaseBillTotal($purchase),
                        outstandingBeforeAllocations: $this->purchaseOutstandingAmount($purchase),
                        allocatedTotal: $this->purchaseAllocatedAmount($purchase),
                    )->value,
                ]);
            });
    }

    private function normalizeAllocations(array $allocations, string $billKey): array
    {
        return collect($allocations)
            ->map(function ($allocation) use ($billKey): ?array {
                $billId = data_get($allocation, 'bill_id') ?? data_get($allocation, $billKey);
                $amount = (float) data_get($allocation, 'amount', 0);

                if (!$billId || $amount <= 0) {
                    return null;
                }

                return [
                    'bill_id' => (string) $billId,
                    'amount' => $amount,
                ];
            })
            ->filter()
            ->groupBy('bill_id')
            ->map(fn (Collection $rows, string $billId) => [
                'bill_id' => $billId,
                'amount' => (float) $rows->sum('amount'),
            ])
            ->values()
            ->all();
    }

    private function loadSalesForAllocations(array $allocations): Collection
    {
        $saleIds = collect($allocations)->pluck('bill_id')->all();

        return Sale::query()
            ->with(['items', 'transaction.lines.account', 'saleReceives' => fn ($query) => $query->whereNull('deleted_at')])
            ->whereIn('id', $saleIds)
            ->get()
            ->keyBy('id');
    }

    private function loadPurchasesForAllocations(array $allocations): Collection
    {
        $purchaseIds = collect($allocations)->pluck('bill_id')->all();

        return Purchase::query()
            ->with(['items', 'transaction.lines.account', 'purchasePayments' => fn ($query) => $query->whereNull('deleted_at')])
            ->whereIn('id', $purchaseIds)
            ->get()
            ->keyBy('id');
    }

    private function resolvePaymentStatus(float $billTotal, float $outstandingBeforeAllocations, float $allocatedTotal): PaymentStatus
    {
        $remaining = max($outstandingBeforeAllocations - $allocatedTotal, 0);

        if ($remaining <= 0) {
            return PaymentStatus::Paid;
        }

        if ($allocatedTotal > 0 || $outstandingBeforeAllocations < $billTotal) {
            return PaymentStatus::PartiallyPaid;
        }

        return PaymentStatus::Unpaid;
    }

    private function saleBillTotal(Sale $sale): float
    {
        return (float) $sale->items->sum(function ($item) use ($sale): float {
            $rowTotal = (float) $item->quantity * (float) $item->unit_price;
            $itemDiscount = (float) ($item->discount ?? 0);
            $saleDiscount = 0.0;

            if ($sale->discount_type === 'percentage') {
                $saleDiscount = $rowTotal * ((float) ($sale->discount ?? 0) / 100);
            } else {
                $saleDiscount = (float) ($sale->discount ?? 0);
            }

            return $rowTotal - $itemDiscount - $saleDiscount;
        });
    }

    private function purchaseBillTotal(Purchase $purchase): float
    {
        return (float) $purchase->items->sum(function ($item) use ($purchase): float {
            $rowTotal = (float) $item->quantity * (float) $item->unit_price;
            $itemDiscount = (float) ($item->discount ?? 0);
            $billDiscount = 0.0;

            if ($purchase->discount_type === 'percentage') {
                $billDiscount = $rowTotal * ((float) ($purchase->discount ?? 0) / 100);
            } else {
                $billDiscount = (float) ($purchase->discount ?? 0);
            }

            return $rowTotal - $itemDiscount - $billDiscount;
        });
    }

    private function saleOutstandingAmount(Sale $sale): float
    {
        $transaction = $sale->transaction;

        if (!$transaction) {
            return 0.0;
        }

        $rate = (float) ($transaction->rate ?? 1);

        return (float) $transaction->lines
            ->filter(fn ($line) => (float) $line->debit > 0 && ($line->account?->slug ?? null) === 'account-receivable')
            ->sum(fn ($line) => (float) $line->debit * $rate);
    }

    private function purchaseOutstandingAmount(Purchase $purchase): float
    {
        $transaction = $purchase->transaction;

        if (!$transaction) {
            return 0.0;
        }

        $rate = (float) ($transaction->rate ?? 1);

        return (float) $transaction->lines
            ->filter(fn ($line) => (float) $line->credit > 0 && ($line->account?->slug ?? null) === 'account-payable')
            ->sum(fn ($line) => (float) $line->credit * $rate);
    }

    private function saleAllocatedAmount(Sale $sale): float
    {
        return (float) $sale->saleReceives->sum('amount');
    }

    private function purchaseAllocatedAmount(Purchase $purchase): float
    {
        return (float) $purchase->purchasePayments->sum('amount');
    }

    private function saleRemainingAmount(Sale $sale): float
    {
        return max($this->saleOutstandingAmount($sale) - $this->saleAllocatedAmount($sale), 0);
    }

    private function purchaseRemainingAmount(Purchase $purchase): float
    {
        return max($this->purchaseOutstandingAmount($purchase) - $this->purchaseAllocatedAmount($purchase), 0);
    }
}

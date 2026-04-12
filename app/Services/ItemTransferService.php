<?php

namespace App\Services;

use App\Enums\TransferStatus;
use App\Models\ItemTransfer\ItemTransfer;
use App\Models\ItemTransfer\ItemTransferItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;
use App\Models\Transaction\Transaction;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Services\DateConversionService;

class ItemTransferService
{
    private $dateConversionService;
    public function __construct(
        private StockService $stockService,
        private ActivityLogService $activityLogService,
    ) {}

    /**
     * Create a new item transfer
     */
    public function createTransfer(array $data): ItemTransfer
    {
        return DB::transaction(function () use ($data) {
            // Validate stock availability for all items
            $this->validateStockAvailability($data['items'], $data['from_warehouse_id']);

            // Create transfer record
            $transfer = ItemTransfer::create([
                'date' => $this->dateConversionService->toGregorian($data['date']),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'status' => TransferStatus::PENDING,
                'transfer_cost' => $data['transfer_cost'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ]);

            // Create transfer items
            foreach ($data['items'] as $itemData) {
                ItemTransferItem::create([
                    'item_transfer_id' => $transfer->id,
                    'item_id' => $itemData['item_id'],
                    'batch' => $itemData['batch'] ?? null,
                    'expire_date' => $itemData['expire_date'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'measure_id' => $itemData['measure_id'],
                    'unit_price' => $itemData['unit_price'] ?? null,
                ]);
            }

            $transfer->load('items');

            $this->activityLogService->logCreate(
                reference: $transfer,
                module: 'item_transfer',
                description: "Item transfer #{$transfer->id} created.",
                newValues: [
                    'date' => $transfer->date?->toDateString(),
                    'status' => $transfer->status?->value ?? $transfer->status,
                    'from_warehouse_id' => $transfer->from_warehouse_id,
                    'to_warehouse_id' => $transfer->to_warehouse_id,
                    'transfer_cost' => (float) ($transfer->transfer_cost ?? 0),
                    'item_count' => $transfer->items->count(),
                ],
                metadata: [
                    'action' => 'item_transfer_create',
                ],
            );

            return $transfer;
        });
    }

    /**
     * Update an existing transfer
     */
    public function updateTransfer(ItemTransfer $transfer, array $data): ItemTransfer
    {
        return DB::transaction(function () use ($transfer, $data) {
            $beforeState = [
                'date' => $transfer->date?->toDateString(),
                'status' => $transfer->status?->value ?? $transfer->status,
                'from_warehouse_id' => $transfer->from_warehouse_id,
                'to_warehouse_id' => $transfer->to_warehouse_id,
                'transfer_cost' => (float) ($transfer->transfer_cost ?? 0),
                'item_count' => $transfer->items()->count(),
            ];

            // If transfer is completed, cannot update
            if ($transfer->status === TransferStatus::COMPLETED) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot update a completed transfer.'],
                ]);
            }

            // If transfer was cancelled and we're reactivating, validate stock
            if ($transfer->status === TransferStatus::CANCELLED && isset($data['status']) && $data['status'] === TransferStatus::PENDING->value) {
                $this->validateStockAvailability($data['items'] ?? $transfer->items->toArray(), $data['from_warehouse_id'] ?? $transfer->from_warehouse_id);
            }

            // Update transfer record
            $transfer->update([
                'date' => $this->dateConversionService->toGregorian($data['date']) ?? $transfer->date,
                'from_warehouse_id' => $data['from_warehouse_id'] ?? $transfer->from_warehouse_id,
                'to_warehouse_id' => $data['to_warehouse_id'] ?? $transfer->to_warehouse_id,
                'status' => $data['status'] ?? $transfer->status,
                'transfer_cost' => $data['transfer_cost'] ?? $transfer->transfer_cost,
                'remarks' => $data['remarks'] ?? $transfer->remarks,
            ]);

            // Update items if provided
            if (isset($data['items'])) {
                $transfer->items()->delete();
                foreach ($data['items'] as $itemData) {
                    ItemTransferItem::create([
                        'item_transfer_id' => $transfer->id,
                        'item_id' => $itemData['item_id'],
                        'batch' => $itemData['batch'] ?? null,
                        'expire_date' => $itemData['expire_date'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'measure_id' => $itemData['measure_id'],
                        'unit_price' => $itemData['unit_price'] ?? null,
                    ]);
                }
            }

            $transfer->load('items');

            $this->activityLogService->logUpdate(
                reference: $transfer,
                before: $beforeState,
                after: [
                    'date' => $transfer->date?->toDateString(),
                    'status' => $transfer->status?->value ?? $transfer->status,
                    'from_warehouse_id' => $transfer->from_warehouse_id,
                    'to_warehouse_id' => $transfer->to_warehouse_id,
                    'transfer_cost' => (float) ($transfer->transfer_cost ?? 0),
                    'item_count' => $transfer->items->count(),
                ],
                module: 'item_transfer',
                description: "Item transfer #{$transfer->id} updated.",
                metadata: [
                    'action' => 'item_transfer_update',
                ],
            );

            return $transfer;
        });
    }

    /**
     * Complete a transfer (trigger stock updates)
     */
    public function completeTransfer(ItemTransfer $transfer): ItemTransfer
    {
        return DB::transaction(function () use ($transfer) {
            if ($transfer->status === TransferStatus::COMPLETED) {
                throw ValidationException::withMessages([
                    'status' => ['Transfer is already completed.'],
                ]);
            }

            if ($transfer->status === TransferStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot complete a cancelled transfer.'],
                ]);
            }

            // Validate stock availability
            $this->validateStockAvailability($transfer->items->toArray(), $transfer->from_warehouse_id);

            // Create transaction for transfer cost
            if($transfer->transfer_cost>0){
                    $transactionService = app(TransactionService::class);
                    $glAccount = Cache::get('gl_accounts');
                    $homeCurrency = Cache::get('home_currency');
                    $transactionService->post(
                        header: [
                            'currency_id' => $homeCurrency->id,
                            'rate' => 1,
                            'date' => $this->dateConversionService->toGregorian($transfer->date),
                            'remark' => 'Transfer cost for item transfer ' . $transfer->id,
                            'reference_type' => ItemTransfer::class,
                            'reference_id' => $transfer->id,
                            'status' => 'posted',
                        ],
                        lines: [
                            [
                                'account_id' => $glAccount['cash-in-hand'],
                                'debit' => 0,
                                'credit' => $transfer->transfer_cost,
                                'remark' => $transfer->remarks,
                            ],
                            [
                                'account_id' => $glAccount['other-expenses'],
                                'debit' => $transfer->transfer_cost,
                                'credit' => 0,
                                'remark' => $transfer->remarks,
                            ],
                        ],
                    );
                }
            // Process each item
            foreach ($transfer->items as $item) {
                // Remove stock from source warehouse
                $stock = $this->stockService->post([
                    'item_id'         => $item->item_id,
                    'movement_type'   => StockMovementType::OUT->value,
                    'unit_measure_id' => $item->measure_id, // from item form
                    'quantity'        => (float) $item->quantity,
                    'source'          => StockSourceType::ITEM_TRANSFER->value,
                    'unit_cost'       => (float) $item->unit_price,
                    'status'          => StockStatus::DRAFT->value,
                    'batch'           => $item->batch ?? null,
                    'date'            => $this->dateConversionService->toGregorian($transfer->date),
                    'expire_date'     => $item->expire_date ?? null,
                    'size_id'         => $item->size_id ?? null,
                    'warehouse_id'    => $transfer->from_warehouse_id,
                    'branch_id'       => $transfer->branch_id,
                    'reference_type'  => ItemTransfer::class,
                    'reference_id'    => $transfer->id,
                ]);

                $stock = $this->stockService->post([
                    'item_id'         => $item->item_id,
                    'movement_type'   => StockMovementType::IN->value,
                    'unit_measure_id' => $item->measure_id, // from item form
                    'quantity'        => (float) $item->quantity,
                    'source'          => StockSourceType::ITEM_TRANSFER->value,
                    'unit_cost'       => (float) $item->unit_price,
                    'status'          => StockStatus::DRAFT->value,
                    'batch'           => $item->batch ?? null,
                    'date'            => $transfer->date,
                    'expire_date'     => $item->expire_date ?? null,
                    'size_id'         => $item->size_id ?? null,
                    'warehouse_id'    => $transfer->to_warehouse_id,
                    'branch_id'       => $transfer->branch_id,
                    'reference_type'  => ItemTransfer::class,
                    'reference_id'    => $transfer->id,
                ]);
            }

            // Update transfer status
            $oldStatus = $transfer->status?->value ?? $transfer->status;
            $transfer->update(['status' => TransferStatus::COMPLETED]);

            $transfer->load('items');

            $this->activityLogService->logAction(
                eventType: 'completed',
                reference: $transfer,
                module: 'item_transfer',
                description: "Item transfer #{$transfer->id} completed.",
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $transfer->status?->value ?? $transfer->status],
                metadata: [
                    'action' => 'item_transfer_complete',
                    'item_count' => $transfer->items->count(),
                ],
            );

            return $transfer;
        });
    }

    /**
     * Cancel a transfer (revert stock changes if already completed)
     */
    public function cancelTransfer(ItemTransfer $transfer): ItemTransfer
    {
        return DB::transaction(function () use ($transfer) {
            if ($transfer->status === TransferStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => ['Transfer is already cancelled.'],
                ]);
            }

            // If transfer was completed, revert stock changes
            if ($transfer->status === TransferStatus::COMPLETED) {

                // Reverse transaction for transfer cost
                if($transfer->transfer_cost>0){
                    $transactionService = app(TransactionService::class);
                    $transactionService->reverse( Transaction::where('reference_type', ItemTransfer::class)
                    ->where('reference_id', $transfer->id)
                    ->first());
                }
                // Find and delete stock records related to this transfer
                \App\Models\Inventory\Stock::where('source_type', ItemTransfer::class)
                    ->where('source_id', $transfer->id)
                    ->delete();

                \App\Models\Inventory\StockOut::where('source_type', ItemTransfer::class)
                    ->where('source_id', $transfer->id)
                    ->delete();
            }

            // Update transfer status
            $oldStatus = $transfer->status?->value ?? $transfer->status;
            $transfer->update(['status' => TransferStatus::CANCELLED]);

            $transfer->load('items');

            $this->activityLogService->logAction(
                eventType: 'cancelled',
                reference: $transfer,
                module: 'item_transfer',
                description: "Item transfer #{$transfer->id} cancelled.",
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $transfer->status?->value ?? $transfer->status],
                metadata: [
                    'action' => 'item_transfer_cancel',
                    'item_count' => $transfer->items->count(),
                ],
            );

            return $transfer;
        });
    }

    /**
     * Validate stock availability for all items
     */
    private function validateStockAvailability(array $items, string $fromWarehouseId): void
    {
        foreach ($items as $item) {
            $itemId = is_array($item) ? $item['item_id'] : $item->item_id;
            $quantity = is_array($item) ? $item['quantity'] : $item->quantity;

            // dd($quantity);
            $stockLevel = $this->stockService->getStockLevel($itemId, $fromWarehouseId, $item['batch'] ?? null, $item['expire_date'] ?? null);
            if ($stockLevel['available'] < $quantity) {
                $itemModel = \App\Models\Inventory\Item::find($itemId);
                $itemName = $itemModel->name ?? 'Unknown';
                throw ValidationException::withMessages([
                    'items' => ["Insufficient stock for item '{$itemName}'. Available: {$stockLevel['available']}, Required: {$quantity}."],
                ]);
            }
        }
    }
}

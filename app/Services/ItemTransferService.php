<?php

namespace App\Services;

use App\Enums\TransferStatus;
use App\Models\ItemTransfer\ItemTransfer;
use App\Models\ItemTransfer\ItemTransferItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemTransferService
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Create a new item transfer
     */
    public function createTransfer(array $data): ItemTransfer
    {
        return DB::transaction(function () use ($data) {
            // Validate stock availability for all items
            $this->validateStockAvailability($data['items'], $data['from_store_id']);

            // Create transfer record
            $transfer = ItemTransfer::create([
                'date' => $data['date'],
                'from_store_id' => $data['from_store_id'],
                'to_store_id' => $data['to_store_id'],
                'status' => TransferStatus::PENDING,
                'transfer_cost' => $data['transfer_cost'] ?? null,
                'branch_id' => $data['branch_id'],
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
                    'branch_id' => $data['branch_id'],
                ]);
            }

            return $transfer->load('items');
        });
    }

    /**
     * Update an existing transfer
     */
    public function updateTransfer(ItemTransfer $transfer, array $data): ItemTransfer
    {
        return DB::transaction(function () use ($transfer, $data) {
            // If transfer is completed, cannot update
            if ($transfer->status === TransferStatus::COMPLETED) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot update a completed transfer.'],
                ]);
            }

            // If transfer was cancelled and we're reactivating, validate stock
            if ($transfer->status === TransferStatus::CANCELLED && isset($data['status']) && $data['status'] === TransferStatus::PENDING->value) {
                $this->validateStockAvailability($data['items'] ?? $transfer->items->toArray(), $data['from_store_id'] ?? $transfer->from_store_id);
            }

            // Update transfer record
            $transfer->update([
                'date' => $data['date'] ?? $transfer->date,
                'from_store_id' => $data['from_store_id'] ?? $transfer->from_store_id,
                'to_store_id' => $data['to_store_id'] ?? $transfer->to_store_id,
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
                        'branch_id' => $transfer->branch_id,
                    ]);
                }
            }

            return $transfer->load('items');
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
            $this->validateStockAvailability($transfer->items->toArray(), $transfer->from_store_id);

            // Process each item
            foreach ($transfer->items as $item) {
                // Remove stock from source store
                $stockOut = $this->stockService->removeStock([
                    'item_id' => $item->item_id,
                    'quantity' => $item->quantity,
                    'unit_measure_id' => $item->measure_id,
                    'unit_price' => $item->unit_price ?? 0,
                    'date' => $transfer->date,
                ], $transfer->from_store_id, ItemTransfer::class, $transfer->id);

                // Get source stock details for transfer
                $sourceStock = \App\Models\Inventory\Stock::find($stockOut->stock_id);

                // Add stock to destination store
                $this->stockService->addStock([
                    'item_id' => $item->item_id,
                    'unit_measure_id' => $item->measure_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price ?? $sourceStock->unit_price ?? 0,
                    'batch' => $item->batch ?? $sourceStock->batch ?? null,
                    'expire_date' => $item->expire_date ?? $sourceStock->expire_date ?? null,
                    'free' => $sourceStock->free ?? 0,
                    'discount' => $sourceStock->discount ?? 0,
                    'tax' => $sourceStock->tax ?? 0,
                ], $transfer->to_store_id, ItemTransfer::class, $transfer->id, $transfer->date);
            }

            // Update transfer status
            $transfer->update(['status' => TransferStatus::COMPLETED]);

            return $transfer->load('items');
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
                // Find and delete stock records related to this transfer
                \App\Models\Inventory\Stock::where('source_type', ItemTransfer::class)
                    ->where('source_id', $transfer->id)
                    ->delete();

                \App\Models\Inventory\StockOut::where('source_type', ItemTransfer::class)
                    ->where('source_id', $transfer->id)
                    ->delete();
            }

            // Update transfer status
            $transfer->update(['status' => TransferStatus::CANCELLED]);

            return $transfer->load('items');
        });
    }

    /**
     * Validate stock availability for all items
     */
    private function validateStockAvailability(array $items, string $fromStoreId): void
    {
        foreach ($items as $item) {
            $itemId = is_array($item) ? $item['item_id'] : $item->item_id;
            $quantity = is_array($item) ? $item['quantity'] : $item->quantity;
            
            $stockLevel = $this->stockService->getStockLevel($itemId, $fromStoreId);
            
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

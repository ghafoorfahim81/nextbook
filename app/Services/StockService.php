<?php
// app/Services/StockService.php

namespace App\Services;

use App\Models\Inventory\Stock;
use App\Models\Inventory\StockOut;
use App\Support\Inertia\CacheKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
class StockService
{
    /**
     * Add stock from various sources
     */
    public function addStock(array $data, $warehouseId, string $sourceType, $sourceId = null, $date = null): Stock
    {
        return DB::transaction(function () use ($data, $warehouseId, $sourceType, $sourceId, $date) {
            $stockData = $this->validateStockData($data);

            $stockData = array_merge($stockData, [
                'date' => $date,
                'warehouse_id' => $warehouseId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'size_id' => $data['size_id'] ?? null,  // from item form
            ]);

            $stock = Stock::create($stockData);
            Cache::forget(CacheKey::forCompanyBranchLocale(request(), 'items'));
            return $stock;
        });
    }

    public function updateStock(array $data, $warehouseId, string $sourceType, $sourceId = null, $date = null): Stock
    {
        return DB::transaction(function () use ($data, $warehouseId, $sourceType, $sourceId, $date) {
            $stockData = $this->validateStockData($data);
            $stockData['date'] = $date;
            $stockData['warehouse_id'] = $warehouseId;
            $stockData['source_type'] = $sourceType;
            $stockData['source_id'] = $sourceId;
            $stockData['size_id'] = $data['size_id'] ?? null;
            $stockData['unit_measure_id'] = $data['unit_measure_id'] ?? null;
            $stockData['quantity'] = $data['quantity'] ?? 0;
            $stockData['unit_price'] = $data['unit_price'] ?? 0;
            $stockData['free'] = $data['free'] ?? 0;
            $stockData['discount'] = $data['discount'] ?? 0;
            $stockData['tax'] = $data['tax'] ?? 0;
            $stockData['date'] = $date;
            $stockData['warehouse_id'] = $warehouseId;
            $stockData['source_type'] = $sourceType;
            $stockData['source_id'] = $sourceId;
            $stock = Stock::updateOrCreate($stockData);
            Cache::forget(CacheKey::forCompanyBranchLocale(request(), 'items'));
            return $stock;
        });

    }

    /**
     * Remove stock for various reasons
     */
    public function removeStock(array $data, $warehouseId, string $sourceType, $sourceId = null): StockOut
    {
        return DB::transaction(function () use ($data, $warehouseId, $sourceType, $sourceId) {
            $stockOutData = $this->validateStockOutData($data);

            // Override warehouse_id with the passed parameter
            $stockOutData['warehouse_id'] = $warehouseId;
            $stockOutData['size_id'] = $data['size_id'] ?? null; // from item form
            $availableStock = $this->getAvailableStock(
                $stockOutData['item_id'],
                $stockOutData['warehouse_id'],
                $stockOutData['quantity'],
                $stockOutData['size_id']
            );

            // If there is no stock available in the selected warehouse, stop early
            if ($availableStock->isEmpty()) {
                throw ValidationException::withMessages([
                    'item_id' => ['Stock not available in the selected warehouse.'],
                ]);
            }

            // If total available across batches is less than requested, stop
            $totalAvailable = $availableStock->sum('available_quantity');
            if ($totalAvailable < $stockOutData['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => ["Insufficient stock. Available: {$totalAvailable}, Required: {$stockOutData['quantity']}."],
                ]);
            }

            $stockOutRecords = [];

            foreach ($availableStock as $stock) {
                $quantityToTake = min($stock->available_quantity, $stockOutData['quantity']);

                $stockOutRecord = StockOut::create([
                    'stock_id' => $stock->id,
                    'item_id' => $stockOutData['item_id'],
                    'quantity' => $quantityToTake,
                    'unit_price' => $stockOutData['unit_price'] ?? $stock->unit_price,
                    'free' => $stockOutData['free'] ?? $stock->free,
                    'tax' => $stockOutData['tax'] ?? $stock->tax,
                    'discount' => $stockOutData['discount'] ?? $stock->discount,
                    'date' => $stockOutData['date'] ?? now(),
                    'batch' => $stock->batch,
                    'unit_measure_id' => $stockOutData['unit_measure_id'] ?? $stock->unit_measure_id,
                    'warehouse_id' => $stock->warehouse_id,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'size_id' => $stockOutData['size_id'] ?? null,
                ]);

                $stockOutRecords[] = $stockOutRecord;
                $stockOutData['quantity'] -= $quantityToTake;

                if ($stockOutData['quantity'] <= 0) break;
            }
            Cache::forget(CacheKey::forCompanyBranchLocale(request(), 'items'));

            return $stockOutRecords[0];
        });
    }

    /**
     * Get current stock level for an item in a warehouse
     */
    public function getStockLevel(string $itemId, string $warehouseId): array
    {
        $totalStock = Stock::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');

        $totalOut = StockOut::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');

        return [
            'available' => $totalStock - $totalOut,
            'total_in' => $totalStock,
            'total_out' => $totalOut,
        ];
    }

    /**
     * Get available stock batches (FIFO)
     */
    private function getAvailableStock(string $itemId, string $warehouseId, float $requiredQuantity, ?string $sizeId = null)
    {
        return Stock::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->when($sizeId, fn ($q) => $q->where('size_id', $sizeId))
            ->where('quantity', '>', 0)
            ->orderBy('date', 'asc') // FIFO - oldest first
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($stock) {
                $stock->available_quantity = $stock->quantity - $stock->stockOuts->sum('quantity');
                return $stock;
            })
            ->where('available_quantity', '>', 0)
            ->values();
    }

    /**
     * Transfer stock between warehouses
     */
    public function transferStock(array $transferData): array
    {
        return DB::transaction(function () use ($transferData) {
            // Remove from source warehouse
            $stockOut = $this->removeStock([
                'item_id' => $transferData['item_id'],
                'quantity' => $transferData['quantity'],
                'date' => $transferData['date'],
            ], $transferData['from_warehouse_id'], 'transfer', $transferData['transfer_id']);

            // Add to destination warehouse
            $sourceStock = Stock::find($stockOut->stock_id);

            $stockIn = $this->addStock([
                'item_id' => $transferData['item_id'],
                'unit_measure_id' => $sourceStock->unit_measure_id,
                'quantity' => $transferData['quantity'],
                'unit_price' => $sourceStock->unit_price,
                'free' => $sourceStock->free,
                'batch' => $sourceStock->batch,
                'discount' => $sourceStock->discount,
                'tax' => $sourceStock->tax,
                'date' => $transferData['date'],
                'expire_date' => $sourceStock->expire_date,
                'size_id' => $sourceStock->size_id,
            ], $transferData['to_warehouse_id'], 'transfer', $transferData['transfer_id']);

            return [
                'out' => $stockOut,
                'in' => $stockIn,
            ];
        });
    }

    /**
     * Stock adjustment (increase/decrease)
     */
    public function adjustStock(array $adjustmentData): array
    {
        return DB::transaction(function () use ($adjustmentData) {
            $results = [];
            $warehouseId = $adjustmentData['warehouse_id'] ?? null;

            if ($adjustmentData['type'] === 'increase') {
                $results['in'] = $this->addStock([
                    'item_id' => $adjustmentData['item_id'],
                    'unit_measure_id' => $adjustmentData['unit_measure_id'],
                    'quantity' => $adjustmentData['quantity'],
                    'unit_price' => $adjustmentData['unit_price'] ?? ($adjustmentData['cost'] ?? 0),
                    'free' => $adjustmentData['free'] ?? 0,
                    'batch' => $adjustmentData['batch'] ?? null,
                    'discount' => $adjustmentData['discount'] ?? 0,
                    'tax' => $adjustmentData['tax'] ?? 0,
                    'date' => $adjustmentData['date'],
                    'expire_date' => $adjustmentData['expire_date'] ?? null,
                    'size_id' => $adjustmentData['size_id'] ?? null,
                ], $warehouseId, 'adjustment', $adjustmentData['adjustment_id'], $adjustmentData['date'] ?? null);
            } else {
                $results['out'] = $this->removeStock([
                    'item_id' => $adjustmentData['item_id'],
                    'quantity' => $adjustmentData['quantity'],
                    'date' => $adjustmentData['date'],
                ], $warehouseId, 'adjustment', $adjustmentData['adjustment_id']);
            }

            return $results;
        });
    }

    /**
     * Validation methods
     */
    private function validateStockData(array $data): array
    {
        return validator($data, [
            'item_id' => 'required|exists:items,id',
            'unit_measure_id' => 'required|exists:unit_measures,id',
            'quantity' => 'required|numeric|min:0.0000001',
            'unit_price' => 'required|numeric|min:0',
            'free' => 'nullable|numeric|min:0',
            'batch' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'date' => 'nullable|date',
            'expire_date' => 'nullable|date',
            'size_id' => 'nullable|exists:sizes,id',
        ])->validate();
    }

    private function validateStockOutData(array $data): array
    {
        return validator($data, [
            'item_id' => 'required|exists:items,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01', 
            'unit_price' => 'nullable|numeric|min:0',
            'free' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'unit_measure_id' => 'nullable|exists:unit_measures,id',
            'date' => 'nullable|date',
            'size_id' => 'nullable|exists:sizes,id',
        ])->validate();
    }


}

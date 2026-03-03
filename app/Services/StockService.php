<?php

namespace App\Services;

use App\Enums\StockStatus;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\InventorySetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Entry point
     */
    public function post(array $data): void
    { 
        DB::transaction(function () use ($data) {

            $item = Item::lockForUpdate()->findOrFail($data['item_id']);

            if ($data['movement_type'] === 'in') {
                $this->handleIn($item, $data);
            } else {
                $this->handleOut($item, $data);
            }
        });
    }

    /**
     * Handle Stock IN
     */
    protected function handleIn(Item $item, array $data): void
    {
        $this->validateBatch($item, $data);

        StockMovement::create([
            ...$data,
            'qty_remaining' => $data['quantity'],
        ]);

        $this->increaseBalance($data);
    }

    /**
     * Handle Stock OUT
     */
    protected function handleOut(Item $item, array $data): void
    {
        $this->validateStockAvailability($data);

        $method = $this->getCostingMethod($data['branch_id']);

        if ($method === 'fifo') {
            $this->deductFIFO($item, $data);
        } else {
            $this->deductWeightedAverage($item, $data);
        }

        $this->decreaseBalance($data);
    }

    /**
     * FIFO Deduction
     */
    protected function deductFIFO(Item $item, array $data): void
    {
        $remaining = $data['quantity'];

        $query = StockMovement::query()
            ->where('branch_id', $data['branch_id'])
            ->where('item_id', $data['item_id'])
            ->where('warehous_id', $data['warehous_id'])
            ->where('movement_type', 'IN')
            ->where('qty_remaining', '>', 0);

        if ($item->is_batch_tracked) {
            $query->where('batch', $data['batch']);
        }

        $inMovements = $query
            ->orderBy('date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($inMovements as $movement) {

            if ($remaining <= 0) break;

            $deductQty = min($movement->qty_remaining, $remaining);

            $movement->decrement('qty_remaining', $deductQty);

            StockMovement::create([
                ...$data,
                'quantity' => $deductQty,
                'unit_cost' => $movement->unit_cost,
                'qty_remaining' => null,
            ]);

            $remaining -= $deductQty;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'stock' => 'Insufficient stock for FIFO deduction.'
            ]);
        }
    }

    /**
     * Weighted Average Deduction
     */
    protected function deductWeightedAverage(Item $item, array $data): void
    {
        $balance = StockBalance::query()
            ->where('branch_id', $data['branch_id'])
            ->where('item_id', $data['item_id'])
            ->where('warehouse_id', $data['warehouse_id'])
            ->lockForUpdate()
            ->firstOrFail();

        StockMovement::create([
            ...$data,
            'unit_cost' => $balance->average_cost,
            'qty_remaining' => null,
        ]);
    }

    /**
     * Increase Balance
     */
    protected function increaseBalance(array $data): void
    { 
        $balance = StockBalance::firstOrCreate(
            [
                'branch_id' => $data['branch_id'],
                'item_id' => $data['item_id'],
                'warehouse_id' => $data['warehouse_id'],
                'batch' => $data['batch'] ?? null,
                'expire_date' => $data['expire_date'] ?? null,
                'status' => $data['status'] ?? StockStatus::DRAFT->value,
            ],
            [
                'quantity' => 0,
                'average_cost' => 0,
            ]
        );

        $newQty = $balance->quantity + $data['quantity'];

        $newAvg = $this->calculateNewAverage(
            $balance->quantity,
            $balance->average_cost,
            $data['quantity'],
            $data['unit_cost']
        );

        $balance->update([
            'quantity' => $newQty,
            'average_cost' => $newAvg,
        ]);
    }

    /**
     * Decrease Balance
     */
    protected function decreaseBalance(array $data): void
    {
        $balance = StockBalance::query()
            ->where('branch_id', $data['branch_id'])
            ->where('item_id', $data['item_id'])
            ->where('warehouse_id', $data['warehouse_id'])
            ->lockForUpdate()
            ->firstOrFail();

        if ($balance->quantity < $data['quantity']) {
            throw ValidationException::withMessages([
                'stock' => 'Negative stock is not allowed.'
            ]);
        }

        $balance->decrement('quantity', $data['quantity']);
    }

    /**
     * Average Cost Formula
     */
    protected function calculateNewAverage(
        float $oldQty,
        float $oldAvg,
        float $newQty,
        float $newCost
    ): float {
        if ($oldQty <= 0) {
            return $newCost;
        }

        return (
            ($oldQty * $oldAvg) + ($newQty * $newCost)
        ) / ($oldQty + $newQty);
    }

    /**
     * Validate Batch Rules
     */
    protected function validateBatch(Item $item, array $data): void
    {
        if ($item->is_batch_tracked && empty($data['batch'])) {
            throw ValidationException::withMessages([
                'batch' => 'Batch is required for this item.'
            ]);
        }
    }

    /**
     * Check Stock Availability
     */
    protected function validateStockAvailability(array $data): void
    {
        $balance = StockBalance::query()
            ->where('branch_id', $data['branch_id'])
            ->where('item_id', $data['item_id'])
            ->where('warehouse_id', $data['warehouse_id'])
            ->first();

        if (!$balance || $balance->quantity < $data['quantity']) {
            throw ValidationException::withMessages([
                'stock' => 'Insufficient stock.'
            ]);
        }
    }

    /**
     * Get Costing Method
     */
    protected function getCostingMethod(string $branchId): string
    {
        return auth()->user()->company->costing_method;
    }
}

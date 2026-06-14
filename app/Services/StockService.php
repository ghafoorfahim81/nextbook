<?php

namespace App\Services;

use App\Enums\CostingMethod;
use App\Enums\StockMovementType;
use App\Enums\StockStatus;
use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    private $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->dateConversionService = $dateConversionService;
    }

    /**
     * Entry point
     */
    public function post(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $item = Item::lockForUpdate()->findOrFail($data['item_id']);
            [$movementData, $balanceData, $conversionFactor, $unitCostOverride] = $this->prepareStockPayloads($item, $data);

            if ($data['movement_type'] === StockMovementType::IN->value) {
                return $this->handleIn($item, $movementData, $balanceData);
            } else {
                return $this->handleOut($item, $movementData, $balanceData, $conversionFactor, $unitCostOverride);
            }
        });
    }

    /**
     * Handle Stock IN
     */
    protected function handleIn(Item $item, array $movementData, array $balanceData): array
    {
        $this->validateBatch($item, $movementData);

        $movement = StockMovement::create([
            ...$movementData,
            'expire_date' => $this->normalizeDate($movementData['expire_date']),
            'qty_remaining' => $balanceData['quantity'],
        ]);

        $this->increaseBalance($item, $balanceData);

        return [$movement];
    }

    /**
     * Handle Stock OUT
     */
    protected function handleOut(Item $item, array $movementData, array $balanceData, float $conversionFactor, ?float $unitCostOverride = null): array
    {
        $this->validateStockAvailability($balanceData);
        $method = $this->getCostingMethod($movementData['branch_id']);

        if ($method === CostingMethod::FIFO->value) {
            $allocations = $this->deductFIFO($item, $movementData, $balanceData, $conversionFactor);
            $this->decreaseBalance($balanceData, $allocations);
            return $allocations;
        }

        if ($method === CostingMethod::LIFO->value) {
            $allocations = $this->deductLIFO($item, $movementData, $balanceData, $conversionFactor);
            $this->decreaseBalance($balanceData, $allocations);
            return $allocations;
        }

        $movement = $this->deductWeightedAverage($item, $movementData, $balanceData, $conversionFactor, $unitCostOverride);
        $this->decreaseBalance($balanceData);
        return [$movement];
    }

    /**
     * FIFO Deduction
     */
    protected function deductFIFO(Item $item, array $movementData, array $balanceData, float $conversionFactor): array
    {
        $remaining = $balanceData['quantity'];
        $query = StockMovement::query()
            ->where('branch_id', $balanceData['branch_id'])
            ->where('item_id', $balanceData['item_id'])
            ->where('warehouse_id', $balanceData['warehouse_id'])
            ->where('movement_type', StockMovementType::IN->value)
            ->where('qty_remaining', '>', 0);

        if ($item->is_batch_tracked && !empty($balanceData['batch'])) {
            $query->where('batch', $balanceData['batch']);
        }

        if (!empty($balanceData['expire_date'])) {
            $query->whereDate('expire_date', $this->normalizeDate($balanceData['expire_date']));
        }

        $inMovements = $query
            ->orderByRaw('CASE WHEN expire_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expire_date')
            ->orderBy('date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $allocations = [];
        foreach ($inMovements as $movement) {
            if ($remaining <= 0) {
                break;
            }
            $deductQty = min($movement->qty_remaining, $remaining);
            $movement->qty_remaining = (float) $movement->qty_remaining - $deductQty;
            $movement->status = StockStatus::POSTED->value;
            $movement->save();

            $outMovement = StockMovement::create([
                ...$movementData,
                'batch' => $movement->batch,
                'quantity' => $this->convertFromItemUnit($deductQty, $conversionFactor),
                'date' => $this->normalizeDate($movementData['date']),
                'expire_date' => $this->normalizeDate($movement->expire_date),
                'unit_cost' => $movementData['unit_cost'],
                // 'unit_cost' => $this->convertMovementCostToSelectedUnit($movement, $item, $conversionFactor),
                'qty_remaining' => null,
            ]);

            $allocations[] = [
                'quantity' => $deductQty,
                'batch' => $movement->batch,
                'expire_date' => ($movement->expire_date?->toDateString()),
                'status' => $this->stockStatusValue($movement->status),
                'movement_id' => $movement->id,
                'out_movement_id' => $outMovement->id,
            ];
            $remaining -= $deductQty;
        }
        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'stock' => 'Insufficient stock for FIFO deduction.'
            ]);
        }

        return $allocations;
    }

    /**
     * LIFO Deduction
     */
    protected function deductLIFO(Item $item, array $movementData, array $balanceData, float $conversionFactor): array
    {
        $remaining = $balanceData['quantity'];
        $query = StockMovement::query()
            ->where('branch_id', $balanceData['branch_id'])
            ->where('item_id', $balanceData['item_id'])
            ->where('warehouse_id', $balanceData['warehouse_id'])
            ->where('movement_type', StockMovementType::IN->value)
            ->where('qty_remaining', '>', 0);

        if ($item->is_batch_tracked && !empty($balanceData['batch'])) {
            $query->where('batch', $balanceData['batch']);
        }

        if (!empty($balanceData['expire_date'])) {
            $query->whereDate('expire_date', $this->normalizeDate($balanceData['expire_date']));
        }

        $inMovements = $query
            ->orderByRaw('CASE WHEN expire_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expire_date', 'asc')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->get();

        $allocations = [];
        foreach ($inMovements as $movement) {
            if ($remaining <= 0) {
                break;
            }
            $deductQty = min($movement->qty_remaining, $remaining);
            $movement->qty_remaining = (float) $movement->qty_remaining - $deductQty;
            $movement->status = StockStatus::POSTED->value;
            $movement->save();

            $outMovement = StockMovement::create([
                ...$movementData,
                'batch'        => $movement->batch,
                'quantity'     => $this->convertFromItemUnit($deductQty, $conversionFactor),
                'date'         => $this->normalizeDate($movementData['date']),
                'expire_date'  => $this->normalizeDate($movement->expire_date),
                'unit_cost'    => $movementData['unit_cost'],
                'qty_remaining' => null,
            ]);

            $allocations[] = [
                'quantity'       => $deductQty,
                'batch'          => $movement->batch,
                'expire_date'    => $movement->expire_date?->toDateString(),
                'status'         => $this->stockStatusValue($movement->status),
                'movement_id'    => $movement->id,
                'out_movement_id' => $outMovement->id,
            ];
            $remaining -= $deductQty;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'stock' => 'Insufficient stock for LIFO deduction.'
            ]);
        }

        return $allocations;
    }

    /**
     * Weighted Average Deduction
     */
    protected function deductWeightedAverage(Item $item, array $movementData, array $balanceData, float $conversionFactor, ?float $unitCostOverride = null): StockMovement
    {
        $balance = StockBalance::query()
            ->where('branch_id', $balanceData['branch_id'])
            ->where('item_id', $balanceData['item_id'])
            ->where('warehouse_id', $balanceData['warehouse_id'])
            ->lockForUpdate()
            ->firstOrFail();

        return StockMovement::create([
            ...$movementData,
            'date' => $this->normalizeDate($movementData['date']),
            'expire_date' => $movementData['expire_date'] ? $this->normalizeDate($movementData['expire_date']) : null,
            'unit_cost' => $unitCostOverride ?? $this->convertToSelectedUnitCost((float) $item->avg_cost, $conversionFactor),
            'qty_remaining' => null,
        ]);
    }

    /**
     * Increase Balance
     */
    protected function increaseBalance(Item $item, array $data): void
    {
        $balance = StockBalance::firstOrCreate(
            [
                'branch_id' => $data['branch_id'],
                'item_id' => $data['item_id'],
                'warehouse_id' => $data['warehouse_id'],
                'batch' => $data['batch'] ?? null,
                'expire_date' => $data['expire_date'] ? $this->normalizeDate($data['expire_date']) : null,
            ],
            [
                'quantity' => 0,
                'status' => $data['status'] ?? StockStatus::DRAFT->value,
            ]
        );
        $newQty = $balance->quantity + $data['quantity'];

        $totalQty = (float) StockBalance::where('item_id', $item->id)->sum('quantity');

       $newAvg = $this->calculateNewAverage(
            $totalQty,           // total qty before this receipt
            (float) $item->avg_cost,
            $data['quantity'],
            $data['unit_cost']
        );
        $item->avg_cost = $newAvg;
        $item->save();

        $balance->update([
            'quantity' => $newQty,
        ]);

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
     * Decrease Balance
     */
    protected function decreaseBalance(array $data, ?array $allocations = null): void
    {

        if ($allocations !== null) {
            foreach ($allocations as $allocation) {
                $balances = StockBalance::query()
                    ->where('branch_id', $data['branch_id'])
                    ->where('item_id', $data['item_id'])
                    ->where('warehouse_id', $data['warehouse_id'])
                    ->when($allocation['batch'] !== null, function ($query) use ($allocation) {
                        return $query->where('batch', $allocation['batch']);
                    }, function ($query) {
                        return $query->whereNull('batch');
                    })
                    ->when($allocation['expire_date'] !== null, function ($query) use ($allocation) {
                        return $query->whereDate('expire_date', $allocation['expire_date']);
                    }, function ($query) {
                        return $query->whereNull('expire_date');
                    })
                    ->lockForUpdate()
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get();

                $this->decrementBalances($balances, (float) $allocation['quantity'], $allocation);

                $this->markBalancesAsPosted($balances);
            }

            return;
        }


        $balances = StockBalance::query()
            ->where('branch_id', $data['branch_id'])
            ->where('item_id', $data['item_id'])
            ->where('warehouse_id', $data['warehouse_id'])
            ->when(!empty($data['batch']), function ($query) use ($data) {
                return $query->where('batch', $data['batch']);
            })
            ->when(!empty($data['expire_date']), function ($query) use ($data) {
                return $query->whereDate('expire_date', $this->normalizeDate($data['expire_date']));
            })
            ->lockForUpdate()
            ->orderByRaw('CASE WHEN expire_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expire_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $this->decrementBalances($balances, (float) $data['quantity'], []);
        $this->markBalancesAsPosted($balances);
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
        $available = (float) StockBalance::query()
            ->where('branch_id', $data['branch_id'])
            ->where('item_id', $data['item_id'])
            ->where('warehouse_id', $data['warehouse_id'])
            ->when(!empty($data['batch']), function($query) use ($data) {
                return $query->where('batch', $data['batch']);
            })
            ->when(!empty($data['expire_date']), function($query) use ($data) {
                return $query->whereDate('expire_date', $this->normalizeDate($data['expire_date']));
            })
            ->sum('quantity');

        if ($available < (float) $data['quantity']) {
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
        return \Illuminate\Support\Facades\Cache::get('costing_method')
            ?? Auth::user()?->company?->costing_method?->value
            ?? 'fifo';
    }

    public function getStockLevel(string $itemId, string $warehouseId, ?string $batch = null, ?string $expireDate = null): array
    {
        $totalStock = StockBalance::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->when($batch, function($query) use ($batch) {
                return $query->where('batch', $batch);
            })
            ->when($expireDate, function($query) use ($expireDate) {
                return $query->whereDate('expire_date', $this->normalizeDate($expireDate));
            })
            ->sum('quantity');

        return [
            'available' => $totalStock,
        ];
    }

    protected function decrementBalances($balances, float $quantity, array $allocation): void
    {
        $remaining = $quantity;

        foreach ($balances as $balance) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $balance->quantity;

            if ($available <= 0) {
                continue;
            }

            $deductQty = min($available, $remaining);
            $balance->decrement('quantity', $deductQty);
            $remaining -= $deductQty;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                    'stock' => 'Negative stock is not allowed.',
                    'allocation' => $allocation['expire_date'] ,
                    'quantity' => $quantity,
            ]);
        }
    }

    protected function markBalancesAsPosted($balances): void
    {
        foreach ($balances as $balance) {
            if ($this->stockStatusValue($balance->status) === StockStatus::POSTED->value) {
                continue;
            }

            $balance->status = StockStatus::POSTED->value;
            $balance->save();
        }
    }

    protected function stockStatusValue(mixed $status): string
    {
        return $status instanceof StockStatus ? $status->value : (string) $status;
    }

    protected function prepareStockPayloads(Item $item, array $data): array
    {
        $unitCostOverride = isset($data['unit_cost_override']) ? (float) $data['unit_cost_override'] : null;

        // Strip caller-only keys so they never reach StockMovement::create.
        $movementData = array_diff_key($data, ['unit_cost_override' => null]);
        $balanceData  = $movementData;

        $conversionFactor = $this->resolveConversionFactor($item->unit_measure_id, $data['unit_measure_id']);

        if ($conversionFactor !== 1.0) {
            $balanceData['unit_cost'] = (float) $data['unit_cost'] / $conversionFactor;
            $balanceData['quantity'] = (float) $data['quantity'] * $conversionFactor;
        }

        return [$movementData, $balanceData, $conversionFactor, $unitCostOverride];
    }

    protected function resolveConversionFactor(string $itemUnitMeasureId, string $selectedUnitMeasureId): float
    {
        if ($itemUnitMeasureId === $selectedUnitMeasureId) {
            return 1.0;
        }

        $selectedUnit = (float) UnitMeasure::query()->findOrFail($selectedUnitMeasureId)->unit;
        $itemUnit = (float) UnitMeasure::query()->findOrFail($itemUnitMeasureId)->unit;

        return $selectedUnit / $itemUnit;
    }

    protected function convertFromItemUnit(float $quantity, float $conversionFactor): float
    {
        if ($conversionFactor === 0.0) {
            return $quantity;
        }

        return $quantity / $conversionFactor;
    }

    protected function convertToSelectedUnitCost(float $itemUnitCost, float $conversionFactor): float
    {
        return $itemUnitCost * $conversionFactor;
    }

    protected function convertMovementCostToSelectedUnit(StockMovement $movement, Item $item, float $conversionFactor): float
    {
        $sourceConversionFactor = $this->resolveConversionFactor($item->unit_measure_id, $movement->unit_measure_id);
        $itemUnitCost = (float) $movement->unit_cost / $sourceConversionFactor;
        return $this->convertToSelectedUnitCost($itemUnitCost, $conversionFactor);
    }

    protected function normalizeDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return $this->dateConversionService->toGregorian($date);
    }
}

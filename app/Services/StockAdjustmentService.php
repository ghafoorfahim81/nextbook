<?php

namespace App\Services;

use App\Enums\CostingMethod;
use App\Enums\FinancialPeriodStatus;
use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Account\Account;
use App\Models\Accounting\FinancialPeriod;
use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockAdjustmentItem;
use App\Models\Inventory\StockMovement;
use App\Models\Transaction\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    /** Per-request cache of offset-account ids keyed by slug. */
    private array $offsetAccountIds = [];

    public function __construct(
        private StockService $stockService,
        private TransactionService $transactionService,
        private DateConversionService $dateConversionService,
        private ActivityLogService $activityLogService,
    ) {}

    /**
     * Create a stock adjustment. Direction (in/out) is derived from the reason.
     * Posts immediately or stays draft per the user's transaction preference.
     */
    public function create(array $data): StockAdjustment
    {
        return DB::transaction(function () use ($data) {
            $reason = StockAdjustmentReason::from($data['reason']);
            $direction = $reason->direction();
            $date = $this->dateConversionService->toGregorian($data['date']);
            $postImmediately = (bool) user_preference('transaction.stock_adjustment_post_immediately', true);
            $status = $postImmediately ? TransactionStatus::POSTED->value : TransactionStatus::DRAFT->value;

            $adjustment = StockAdjustment::create([
                'reference' => $this->generateReference($date),
                'date' => $date,
                'type' => $direction->value,
                'reason' => $reason->value,
                'warehouse_id' => $data['warehouse_id'],
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'fiscal_period_id' => $this->resolveFiscalPeriodId($date),
            ]);

            $this->createItemsAndPost($adjustment, $data['items'], $postImmediately);

            $this->activityLogService->logCreate(
                reference: $adjustment,
                module: 'stock_adjustment',
                description: "Stock adjustment {$adjustment->reference} created.",
                newValues: [
                    'reference' => $adjustment->reference,
                    'date' => $adjustment->date?->toDateString(),
                    'type' => $direction->value,
                    'reason' => $reason->value,
                    'status' => $adjustment->status,
                    'warehouse_id' => $adjustment->warehouse_id,
                    'item_count' => count($data['items']),
                ],
                metadata: [
                    'action' => 'stock_adjustment_create',
                ],
            );

            return $adjustment;
        });
    }

    /**
     * Update a draft adjustment: release the old reservations, rebuild the
     * items, payloads and draft transaction from scratch.
     */
    public function update(StockAdjustment $adjustment, array $data): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $data) {
            if ($adjustment->status !== TransactionStatus::DRAFT->value) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft documents can be edited.'],
                ]);
            }

            $reason = StockAdjustmentReason::from($data['reason']);
            $date = $this->dateConversionService->toGregorian($data['date']);

            $adjustment->update([
                'date' => $date,
                'type' => $reason->direction()->value,
                'reason' => $reason->value,
                'warehouse_id' => $data['warehouse_id'],
                'notes' => $data['notes'] ?? null,
                'fiscal_period_id' => $this->resolveFiscalPeriodId($date),
            ]);

            $transaction = Transaction::query()
                ->where('reference_type', StockAdjustment::class)
                ->where('reference_id', $adjustment->id)
                ->first();

            if ($transaction) {
                foreach ((array) data_get($transaction->posting_payload, 'stock_movements', []) as $oldPayload) {
                    $this->stockService->release($oldPayload);
                }
                $transaction->lines()->forceDelete();
                $transaction->forceDelete();
            }

            $adjustment->items()->forceDelete();

            $this->createItemsAndPost($adjustment, $data['items'], postImmediately: false);

            $this->activityLogService->logUpdate(
                reference: $adjustment,
                before: [],
                after: [
                    'reference' => $adjustment->reference,
                    'date' => $adjustment->date?->toDateString(),
                    'reason' => $reason->value,
                    'item_count' => count($data['items']),
                ],
                module: 'stock_adjustment',
                description: "Stock adjustment {$adjustment->reference} updated.",
                metadata: [
                    'action' => 'stock_adjustment_update',
                ],
            );

            return $adjustment;
        });
    }

    /**
     * Post a draft adjustment: turn reservations into real stock movements
     * and post the GL transaction.
     */
    public function post(StockAdjustment $adjustment): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment) {
            if ($adjustment->status !== TransactionStatus::DRAFT->value) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft documents can be posted.'],
                ]);
            }

            $transaction = $adjustment->transaction()->firstOrFail();

            foreach ((array) data_get($transaction->posting_payload, 'stock_movements', []) as $payload) {
                try {
                    $this->stockService->release($payload);
                    $this->stockService->post($payload);
                } catch (ValidationException $e) {
                    $itemName = Item::find($payload['item_id'] ?? null)?->name ?? ($payload['item_id'] ?? '');
                    throw ValidationException::withMessages([
                        'stock' => __('general.cannot_post_insufficient_stock', ['item' => $itemName]),
                    ]);
                }
            }

            $this->transactionService->postDraft($transaction);

            $adjustment->update([
                'status' => TransactionStatus::POSTED->value,
                'updated_by' => Auth::id(),
            ]);

            $this->activityLogService->logAction(
                eventType: 'posted',
                reference: $adjustment,
                module: 'stock_adjustment',
                description: "Stock adjustment {$adjustment->reference} posted.",
                oldValues: ['status' => TransactionStatus::DRAFT->value],
                newValues: ['status' => $adjustment->status],
                metadata: ['action' => 'stock_adjustment_post'],
            );

            return $adjustment;
        });
    }

    /**
     * Reverse a posted adjustment: mirror the GL lines and undo the stock
     * movements through TransactionService (audit-safe, no hard delete).
     */
    public function reverse(StockAdjustment $adjustment, ?string $reason = null): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $reason) {
            if ($adjustment->status !== TransactionStatus::POSTED->value) {
                throw ValidationException::withMessages([
                    'status' => ['Only posted documents can be reversed.'],
                ]);
            }

            $transaction = $adjustment->transaction()->firstOrFail();
            $this->transactionService->reverse($transaction, $reason, $adjustment->reference, StockAdjustment::class);

            $adjustment->update([
                'status' => TransactionStatus::REVERSED->value,
                'updated_by' => Auth::id(),
            ]);

            $this->activityLogService->logAction(
                eventType: 'reversed',
                reference: $adjustment,
                module: 'stock_adjustment',
                description: "Stock adjustment {$adjustment->reference} reversed.",
                oldValues: ['status' => TransactionStatus::POSTED->value],
                newValues: ['status' => $adjustment->status],
                metadata: ['action' => 'stock_adjustment_reverse'],
            );

            return $adjustment;
        });
    }

    /**
     * Release reservations held by a draft before it is deleted.
     */
    public function releaseDraftReservations(StockAdjustment $adjustment): void
    {
        foreach ((array) data_get($adjustment->transaction?->posting_payload, 'stock_movements', []) as $payload) {
            $this->stockService->release($payload);
        }
    }

    // ======================================================
    // Internals
    // ======================================================

    /**
     * Create line records, resolve unit costs, move stock (or reserve for
     * drafts) and post the balanced GL transaction.
     */
    private function createItemsAndPost(StockAdjustment $adjustment, array $items, bool $postImmediately): void
    {
        $reason = $adjustment->reason instanceof StockAdjustmentReason
            ? $adjustment->reason
            : StockAdjustmentReason::from($adjustment->reason);
        $direction = $reason->direction();
        $isOut = $direction === StockMovementType::OUT;
        $date = $adjustment->date instanceof \Carbon\CarbonInterface
            ? $adjustment->date->toDateString()
            : (string) $adjustment->date;
        $status = $adjustment->status;
        $offsetAccountId = $this->resolveOffsetAccountId($reason);
        $glAccounts = Cache::get('gl_accounts');
        $allowInCostOverride = (bool) user_preference('stock_adjustment.allow_in_cost_override', true);

        [$itemModelsById, $unitValuesById] = $this->buildItemLookup($items);

        $lines = [];
        $stockPayloads = [];

        foreach ($items as $line) {
            $itemModel = $itemModelsById[$line['item_id']] ?? null;
            if (!$itemModel) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(Item::class, [$line['item_id']]);
            }

            $quantity = (float) $line['quantity'];

            // OUT costs always come from the costing engine (FIFO/LIFO layer
            // peek or weighted average) — never typed by the user. IN costs may
            // be user-entered when the preference allows it, otherwise they
            // default to the current average so the valuation does not shift.
            if ($isOut) {
                $unitCost = $this->resolveOutUnitCost(
                    itemModel: $itemModel,
                    selectedUnitMeasureId: $line['unit_measure_id'],
                    unitValuesById: $unitValuesById,
                    warehouseId: $adjustment->warehouse_id,
                    branchId: $adjustment->branch_id,
                    quantity: $quantity,
                );
            } else {
                $userCost = isset($line['unit_cost']) && $line['unit_cost'] !== '' && $line['unit_cost'] !== null
                    ? (float) $line['unit_cost']
                    : null;
                $unitCost = ($allowInCostOverride && $userCost !== null)
                    ? $userCost
                    : $this->convertCostToSelectedUnit(
                        (float) $itemModel->avg_cost,
                        $line['unit_measure_id'],
                        $itemModel->unit_measure_id,
                        $unitValuesById
                    );
            }

            StockAdjustmentItem::create([
                'stock_adjustment_id' => $adjustment->id,
                'item_id' => $line['item_id'],
                'unit_measure_id' => $line['unit_measure_id'],
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'batch' => $line['batch'] ?? null,
                'color' => $line['color'] ?? null,
                'expire_date' => !empty($line['expire_date'])
                    ? $this->dateConversionService->toGregorian($line['expire_date'])
                    : null,
                'size_id' => $line['size_id'] ?? null,
                'category_id' => $line['category_id'] ?? $itemModel->category_id,
                'branch_id' => $adjustment->branch_id,
            ]);

            $stockPayloads[] = [
                'item_id' => $line['item_id'],
                'movement_type' => $direction->value,
                'unit_measure_id' => $line['unit_measure_id'],
                'quantity' => $quantity,
                'source' => StockSourceType::STOCK_ADJUSTMENT->value,
                'unit_cost' => $unitCost,
                'unit_cost_override' => $unitCost,
                'status' => StockStatus::POSTED->value,
                'batch' => $line['batch'] ?? null,
                'color' => $line['color'] ?? null,
                'date' => $date,
                'expire_date' => $line['expire_date'] ?? null,
                'size_id' => $line['size_id'] ?? null,
                'warehouse_id' => $adjustment->warehouse_id,
                'branch_id' => $adjustment->branch_id,
                'reference_type' => StockAdjustment::class,
                'reference_id' => $adjustment->id,
            ];

            if ($postImmediately) {
                $this->stockService->post($stockPayloads[array_key_last($stockPayloads)]);
            } else {
                // Draft: hold the stock as reserved so it is visible to other users.
                $this->stockService->reserve($stockPayloads[array_key_last($stockPayloads)]);
            }

            $totalCost = round($unitCost * $quantity, 4);
            $inventoryAccountId = $itemModel->asset_account_id ?? data_get($glAccounts, 'inventory-stock');

            if (!$inventoryAccountId) {
                throw ValidationException::withMessages([
                    'items' => ["No inventory account configured for item '{$itemModel->name}'."],
                ]);
            }

            // OUT: debit offset expense, credit Inventory (asset down, expense up).
            // IN: debit Inventory, credit offset expense (asset up, loss undone).
            $lines[] = [
                'account_id' => $isOut ? $offsetAccountId : $inventoryAccountId,
                'ledger_id' => null,
                'debit' => $totalCost,
                'credit' => 0,
                'remark' => ($isOut ? 'Stock adjustment (' . $reason->value . ') for item: ' : 'Inventory in from adjustment for item: ')
                    . $itemModel->name . ' #' . $adjustment->reference,
                'remark_fa' => 'تعدیل موجودی ' . $itemModel->name . ' #' . $adjustment->reference,
                'remark_ps' => 'د موجودۍ تعدیل ' . $itemModel->name . ' #' . $adjustment->reference,
            ];
            $lines[] = [
                'account_id' => $isOut ? $inventoryAccountId : $offsetAccountId,
                'ledger_id' => null,
                'debit' => 0,
                'credit' => $totalCost,
                'remark' => ($isOut ? 'Inventory out from adjustment for item: ' : 'Stock adjustment (' . $reason->value . ') for item: ')
                    . $itemModel->name . ' #' . $adjustment->reference,
                'remark_fa' => 'تعدیل موجودی ' . $itemModel->name . ' #' . $adjustment->reference,
                'remark_ps' => 'د موجودۍ تعدیل ' . $itemModel->name . ' #' . $adjustment->reference,
            ];
        }

        $homeCurrency = Cache::get('home_currency');

        $this->transactionService->post(
            header: [
                'currency_id' => $homeCurrency->id,
                'rate' => 1,
                'date' => $date,
                'voucher_number' => 'Adjustment #' . $adjustment->reference,
                'remark' => 'Stock adjustment ' . $adjustment->reference . ' (' . $reason->value . ')',
                'status' => $status,
                'reference_type' => StockAdjustment::class,
                'reference_id' => $adjustment->id,
                'posting_payload' => [
                    'stock_movements' => $stockPayloads,
                ],
            ],
            lines: $lines,
        );
    }

    /**
     * Reference like ADJ-2026-0001, sequenced per prefix + year.
     */
    private function generateReference(string $date): string
    {
        $prefix = rtrim((string) user_preference('stock_adjustment.reference_prefix', 'ADJ-'), '-');
        $year = date('Y', strtotime($date) ?: time());
        $base = $prefix . '-' . $year . '-';

        $lastReference = StockAdjustment::withTrashed()
            ->where('reference', 'like', $base . '%')
            ->orderByDesc('reference')
            ->value('reference');

        $next = 1;
        if ($lastReference && preg_match('/(\d+)$/', $lastReference, $matches)) {
            $next = (int) $matches[1] + 1;
        }

        return $base . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function resolveFiscalPeriodId(string $date): ?string
    {
        return FinancialPeriod::query()
            ->where('status', FinancialPeriodStatus::Open->value)
            ->whereDate('start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
            })
            ->value('id');
    }

    /**
     * Offset account for a reason: user preference mapping first (restricted
     * to the 9040/9050 slugs), then the enum default.
     */
    private function resolveOffsetAccountId(StockAdjustmentReason $reason): string
    {
        $mapping = (array) user_preference('stock_adjustment.reason_accounts', []);
        $slug = $mapping[$reason->value] ?? $reason->defaultOffsetAccountSlug();

        if (!in_array($slug, ['inventory-shrinkage-and-wastage', 'inventory-adjustments'], true)) {
            $slug = $reason->defaultOffsetAccountSlug();
        }

        if (!isset($this->offsetAccountIds[$slug])) {
            $accountId = data_get(Cache::get('gl_accounts'), $slug)
                ?? Account::query()->where('slug', $slug)->value('id');

            if (!$accountId) {
                throw ValidationException::withMessages([
                    'reason' => ["Offset account '{$slug}' not found. Please seed accounts 9040/9050 first."],
                ]);
            }

            $this->offsetAccountIds[$slug] = $accountId;
        }

        return $this->offsetAccountIds[$slug];
    }

    /**
     * @return array{0: array<string, Item>, 1: array<string, float>}
     */
    private function buildItemLookup(array $items): array
    {
        $itemIds = collect($items)->pluck('item_id')->filter()->unique()->values();

        $itemModelsById = Item::query()
            ->whereIn('id', $itemIds)
            ->get(['id', 'name', 'unit_measure_id', 'asset_account_id', 'category_id', 'avg_cost'])
            ->keyBy('id')
            ->all();

        $itemUnitMeasureIds = collect($itemModelsById)->pluck('unit_measure_id');
        $selectedUnitMeasureIds = collect($items)->pluck('unit_measure_id')->filter();
        $allUnitMeasureIds = $itemUnitMeasureIds->merge($selectedUnitMeasureIds)->unique()->values();

        $unitValuesById = UnitMeasure::query()
            ->whereIn('id', $allUnitMeasureIds)
            ->pluck('unit', 'id')
            ->map(fn ($value) => (float) $value)
            ->all();

        return [$itemModelsById, $unitValuesById];
    }

    /**
     * Unit cost for an OUT line in the selected unit, honouring the company's
     * costing method: FIFO/LIFO peek the same layers StockService will
     * consume; weighted average uses the item's running average.
     */
    private function resolveOutUnitCost(
        Item $itemModel,
        string $selectedUnitMeasureId,
        array $unitValuesById,
        string $warehouseId,
        ?string $branchId,
        float $quantity,
    ): float {
        $avgCost = (float) $itemModel->avg_cost;
        $method = Cache::get('costing_method', CostingMethod::WEIGHTED_AVERAGE->value);

        if ($method !== CostingMethod::FIFO->value && $method !== CostingMethod::LIFO->value) {
            return $this->convertCostToSelectedUnit($avgCost, $selectedUnitMeasureId, $itemModel->unit_measure_id, $unitValuesById);
        }

        $order = $method === CostingMethod::FIFO->value ? 'asc' : 'desc';

        $selectedUomVal = (float) ($unitValuesById[$selectedUnitMeasureId] ?? 1.0) ?: 1.0;
        $itemUomVal = (float) ($unitValuesById[$itemModel->unit_measure_id] ?? 1.0) ?: 1.0;
        $qtyInItemUnits = $quantity * ($selectedUomVal / $itemUomVal);

        // Mirror the exact ordering StockService::deductFIFO()/deductLIFO() use
        // so the GL cost matches the layers that will actually be consumed.
        $layers = StockMovement::query()
            ->where('item_id', $itemModel->id)
            ->where('warehouse_id', $warehouseId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('movement_type', StockMovementType::IN->value)
            ->where('qty_remaining', '>', 0)
            ->orderByRaw('CASE WHEN expire_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expire_date', 'asc')
            ->orderBy('date', $order)
            ->orderBy('created_at', $order)
            ->orderBy('id', $order)
            ->get(['qty_remaining', 'unit_cost', 'unit_measure_id']);

        if ($layers->isEmpty()) {
            return $this->convertCostToSelectedUnit($avgCost, $selectedUnitMeasureId, $itemModel->unit_measure_id, $unitValuesById);
        }

        $missingUomIds = $layers->pluck('unit_measure_id')->unique()->filter()
            ->reject(fn ($id) => isset($unitValuesById[$id]))->values()->all();
        if (!empty($missingUomIds)) {
            $extra = UnitMeasure::whereIn('id', $missingUomIds)->pluck('unit', 'id')
                ->map(fn ($value) => (float) $value)->all();
            $unitValuesById = array_merge($unitValuesById, $extra);
        }

        $remaining = $qtyInItemUnits;
        $totalCostItemUnit = 0.0;
        $totalQtyConsumed = 0.0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }
            $chunk = min((float) $layer->qty_remaining, $remaining);

            $layerUomVal = (float) ($unitValuesById[$layer->unit_measure_id] ?? 1.0) ?: 1.0;
            $layerConvFactor = $layerUomVal / $itemUomVal;
            $costPerItemUnit = $layerConvFactor > 0
                ? (float) $layer->unit_cost / $layerConvFactor
                : (float) $layer->unit_cost;

            $totalCostItemUnit += $chunk * $costPerItemUnit;
            $totalQtyConsumed += $chunk;
            $remaining -= $chunk;
        }

        if ($totalQtyConsumed <= 0) {
            return $this->convertCostToSelectedUnit($avgCost, $selectedUnitMeasureId, $itemModel->unit_measure_id, $unitValuesById);
        }

        return $this->convertCostToSelectedUnit(
            $totalCostItemUnit / $totalQtyConsumed,
            $selectedUnitMeasureId,
            $itemModel->unit_measure_id,
            $unitValuesById
        );
    }

    /**
     * Convert a cost expressed per item base unit into the selected unit.
     */
    private function convertCostToSelectedUnit(
        float $baseUnitCost,
        string $selectedUnitMeasureId,
        string $itemUnitMeasureId,
        array $unitValuesById
    ): float {
        if ($selectedUnitMeasureId === $itemUnitMeasureId) {
            return $baseUnitCost;
        }

        $selectedUnit = (float) ($unitValuesById[$selectedUnitMeasureId] ?? 0);
        $itemUnit = (float) ($unitValuesById[$itemUnitMeasureId] ?? 0);
        if ($itemUnit === 0.0) {
            return $baseUnitCost;
        }

        return ($selectedUnit * $baseUnitCost) / $itemUnit;
    }
}

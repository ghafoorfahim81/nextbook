<?php

namespace App\Services;

use App\Enums\CostingMethod;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Comparison tolerance for stock quantities.
     *
     * Persisted quantities (stock_balances.quantity, stock_movements.qty_remaining)
     * are numeric(18,4) — 4 decimal places. When a sale uses a sub-unit of the item's
     * base unit (e.g. selling "بسته" of a "کارتن6بسته" item), unit conversion yields a
     * repeating decimal (2 ÷ 6 = 0.33333…) that the DB stores rounded to 0.3333, while
     * the required amount is computed in PHP at full precision (0.33333333). Comparing
     * the two directly reports a false "Insufficient stock". This epsilon ignores
     * shortfalls below the storage resolution.
     */
    private const QUANTITY_EPSILON = 0.0001;

    private $dateConversionService;

    public function __construct(
        DateConversionService $dateConversionService,
        private ItemVariantService $variantService,
    ) {
        $this->dateConversionService = $dateConversionService;
    }

    /**
     * Resolve the variant a stock payload moves, so every write has a real
     * bucketing key regardless of whether the caller's screen has a variant
     * picker yet.
     *
     * An explicit variant_id wins. A caller that omits it falls back to the
     * item's one default variant, exactly as before variants existed.
     */
    protected function resolveVariantId(Item $item, array $data): string
    {
        if (! empty($data['variant_id'])) {
            return $data['variant_id'];
        }

        return $this->variantService->ensureDefault($item)->id;
    }

    /**
     * Entry point
     */
    public function post(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $item = Item::lockForUpdate()->findOrFail($data['item_id']);
            $data['variant_id'] = $this->resolveVariantId($item, $data);
            [$movementData, $balanceData, $conversionFactor, $unitCostOverride] = $this->prepareStockPayloads($item, $data);

            if ($data['movement_type'] === StockMovementType::IN->value) {
                return $this->handleIn($item, $movementData, $balanceData);
            } else {
                return $this->handleOut($item, $movementData, $balanceData, $conversionFactor, $unitCostOverride);
            }
        });
    }

    // ======================================================
    // 📌 RESERVATIONS (unposted drafts)
    // ======================================================

    /**
     * Reserve stock for an unposted draft line. OUT movements (sales) fill
     * reserved_out, IN movements (purchases) fill reserved_in. Creates the balance
     * row if missing so a reservation can exist even at zero on-hand.
     */
    public function reserve(array $data): void
    {
        DB::transaction(function () use ($data) {
            [$balance, $column, $quantity] = $this->resolveReservation($data, createIfMissing: true);

            $balance->{$column} = (float) $balance->{$column} + $quantity;
            $balance->save();
        });
    }

    /**
     * Release a previously reserved draft line (on post, edit, or delete).
     * Floors at zero and is a no-op when no balance row exists.
     */
    public function release(array $data): void
    {
        DB::transaction(function () use ($data) {
            [$balance, $column, $quantity] = $this->resolveReservation($data, createIfMissing: false);

            if (! $balance) {
                return;
            }

            $balance->{$column} = max(0, (float) $balance->{$column} - $quantity);
            $balance->save();
        });
    }

    /**
     * Enforcement check for sales when the reservation preference is ON.
     * Available to other documents = quantity - (reserved_out - this line's own reserved).
     * Throws so the caller can surface a friendly, item-named message. A document is
     * never blocked by its own reservation.
     */
    public function ensureReservedAvailability(array $data): void
    {
        [$balance, $column, $quantity] = $this->resolveReservation($data, createIfMissing: false);

        if (! $balance) {
            return;
        }

        $reservedByOthers = max(0, (float) $balance->reserved_out - $quantity);
        $availableForThis = (float) $balance->quantity - $reservedByOthers;

        if ($availableForThis < $quantity) {
            throw ValidationException::withMessages([
                'stock' => 'Insufficient stock.',
            ]);
        }
    }

    /**
     * Locate (and optionally create + lock) the balance row for a reservation payload,
     * and resolve the target column and the quantity expressed in the item's base unit.
     *
     * @return array{0: ?StockBalance, 1: string, 2: float}
     */
    private function resolveReservation(array $data, bool $createIfMissing): array
    {
        $item = Item::findOrFail($data['item_id']);
        $conversionFactor = $this->resolveConversionFactor($item->unit_measure_id, $data['unit_measure_id']);
        $quantity = (float) $data['quantity'] * $conversionFactor;

        $column = ($data['movement_type'] ?? null) === StockMovementType::IN->value
            ? 'reserved_in'
            : 'reserved_out';

        $keys = [
            'branch_id' => $data['branch_id'],
            'item_id' => $data['item_id'],
            'warehouse_id' => $data['warehouse_id'],
            'variant_id' => $this->resolveVariantId($item, $data),
            'batch' => $data['batch'] ?? null,
            'expire_date' => ! empty($data['expire_date']) ? $this->normalizeDate($data['expire_date']) : null,
        ];

        if ($createIfMissing) {
            $balance = StockBalance::firstOrCreate($keys, [
                'quantity' => 0,
                'status' => $data['status'] ?? StockStatus::DRAFT->value,
            ]);
            $balance = StockBalance::whereKey($balance->id)->lockForUpdate()->first();
        } else {
            $balance = StockBalance::query()
                ->where($keys)
                ->lockForUpdate()
                ->first();
        }

        return [$balance, $column, $quantity];
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
        $method = $this->getCostingMethod($item);

        // Before anything is deducted, so the totals match the ones the
        // original receipt blended against. See unwindAverage().
        if (! empty($balanceData['unwind_average_cost'])) {
            $this->unwindAverage($item, $balanceData);
        }

        if ($method === CostingMethod::FIFO->value) {
            $allocations = $this->deductFIFO($item, $movementData, $balanceData, $conversionFactor, $unitCostOverride);
            $this->decreaseBalance($balanceData, $allocations);
            return $allocations;
        }

        if ($method === CostingMethod::LIFO->value) {
            $allocations = $this->deductLIFO($item, $movementData, $balanceData, $conversionFactor, $unitCostOverride);
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
    protected function deductFIFO(
        Item $item,
        array $movementData,
        array $balanceData,
        float $conversionFactor,
        ?float $unitCostOverride = null,
    ): array {
        return $this->consumeLayers(
            $item,
            $movementData,
            $balanceData,
            $conversionFactor,
            $unitCostOverride,
            fn ($query) => $query
                ->orderByRaw('CASE WHEN expire_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expire_date')
                ->orderBy('date')
                ->orderBy('created_at')
                ->orderBy('id'),
            'FIFO',
        );
    }

    /**
     * LIFO Deduction
     */
    protected function deductLIFO(
        Item $item,
        array $movementData,
        array $balanceData,
        float $conversionFactor,
        ?float $unitCostOverride = null,
    ): array {
        return $this->consumeLayers(
            $item,
            $movementData,
            $balanceData,
            $conversionFactor,
            $unitCostOverride,
            fn ($query) => $query
                ->orderByRaw('CASE WHEN expire_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expire_date', 'asc')
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc'),
            'LIFO',
        );
    }

    /**
     * Take quantity off the IN layers and write one OUT row per layer consumed.
     *
     * FIFO and LIFO differ only in which end of the queue they start from, so
     * they share this and pass their own ordering.
     *
     * `give_back_of` marks an OUT that hands ONE document's goods back — a
     * purchase return. Such a line is not an ordinary issue and must not be
     * costed like one: the supplier credits what they were paid, so the layers
     * that document brought in are emptied first and the OUT is priced at that
     * document's cost. Costing it off the front of the queue instead relieved
     * inventory at an unrelated layer's price (an opening at 50 for goods
     * bought at 100), leaving the ledger and the stock disagreeing by the
     * difference on every return.
     *
     * @param  \Closure  $order  Applies the costing method's layer ordering.
     * @param  string  $method  Named in the insufficient-stock message.
     */
    private function consumeLayers(
        Item $item,
        array $movementData,
        array $balanceData,
        float $conversionFactor,
        ?float $unitCostOverride,
        \Closure $order,
        string $method,
    ): array {
        $remaining = (float) $balanceData['quantity'];
        $allocations = [];

        $giveBackOf = $movementData['give_back_of'] ?? null;
        $pinnedCost = $giveBackOf !== null ? $unitCostOverride : null;

        // The originating document's own layers, then — only if it no longer
        // holds enough, because the goods were sold on — everything else.
        $passes = $giveBackOf !== null ? [$giveBackOf, null] : [null];

        foreach ($passes as $only) {
            if ($remaining <= self::QUANTITY_EPSILON) {
                break;
            }

            $query = StockMovement::query()
                ->where('branch_id', $balanceData['branch_id'])
                ->where('item_id', $balanceData['item_id'])
                ->where('warehouse_id', $balanceData['warehouse_id'])
                ->where('movement_type', StockMovementType::IN->value)
                // A voided layer is stock the business never ended up owning —
                // a reversed receipt, or the compensating row a reversal writes
                // for its audit trail. Left consumable, an issue helped itself
                // to one and was costed at a price from a document that had
                // been undone, which is how an emptied warehouse ended up with
                // a negative inventory balance.
                ->whereNotIn('status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
                ->where('qty_remaining', '>', 0);

            if ($item->is_batch_tracked && !empty($balanceData['batch'])) {
                $query->where('batch', $balanceData['batch']);
            }

            if (!empty($balanceData['expire_date'])) {
                $query->whereDate('expire_date', $this->normalizeDate($balanceData['expire_date']));
            }

            // Only consume incoming stock of the same variant.
            $this->applyVariantFilter($query, $balanceData['variant_id'] ?? null);

            if ($only !== null) {
                $query->where('reference_type', $only['type'])
                    ->where('reference_id', $only['id']);
            }

            $inMovements = $order($query)->lockForUpdate()->get();

            foreach ($inMovements as $movement) {
                if ($remaining <= self::QUANTITY_EPSILON) {
                    break;
                }

                $deductQty = min((float) $movement->qty_remaining, $remaining);
                $movement->qty_remaining = (float) $movement->qty_remaining - $deductQty;

                // Consuming a draft layer posts it — an item opening arrives as
                // a draft and becomes real the moment something is issued
                // against it. Only a draft, though: this used to promote any
                // status it touched, which quietly brought voided rows back to
                // life as posted ones.
                if ($movement->status === StockStatus::DRAFT) {
                    $movement->status = StockStatus::POSTED->value;
                }

                $movement->save();

                $outMovement = StockMovement::create([
                    ...$movementData,
                    'batch' => $movement->batch,
                    'quantity' => $this->convertFromItemUnit($deductQty, $conversionFactor),
                    'date' => $this->normalizeDate($movementData['date']),
                    'expire_date' => $this->normalizeDate($movement->expire_date),
                    // The cost of the layer actually being consumed, not whatever
                    // the caller happened to pass. Two layers at different costs
                    // must produce two OUT rows at those two costs — that is what
                    // FIFO means, and what the stock ledger is read for. A give-back
                    // is the one exception, and says so explicitly.
                    'unit_cost' => $pinnedCost ?? $this->convertMovementCostToSelectedUnit($movement, $item, $conversionFactor),
                    'qty_remaining' => null,
                ]);

                $allocations[] = [
                    'quantity' => $deductQty,
                    'batch' => $movement->batch,
                    'variant_id' => $movement->variant_id,
                    'expire_date' => $movement->expire_date?->toDateString(),
                    'status' => $this->stockStatusValue($movement->status),
                    'movement_id' => $movement->id,
                    'out_movement_id' => $outMovement->id,
                ];

                $remaining -= $deductQty;
            }
        }

        if ($remaining > self::QUANTITY_EPSILON) {
            throw ValidationException::withMessages([
                'stock' => 'Insufficient stock for ' . $method . ' deduction.',
            ]);
        }

        return $allocations;
    }

    /**
     * Put back the quantity an issue took off the FIFO layers.
     *
     * Reversing a document writes a compensating movement, which restores the
     * balance but not the layers: the units the issue consumed stayed consumed,
     * so the live layers drifted below the quantity actually on the shelf and
     * eventually hit zero with stock still in the warehouse. From there FIFO
     * had nothing legitimate left to consume.
     *
     * An issue is split one OUT row per layer and each row carries that layer's
     * own cost, so the row identifies what it came from. Layers are refilled in
     * the reverse of the order they were emptied. A weighted-average item never
     * depletes its layers in the first place, so nothing here matches and
     * nothing is touched.
     */
    public function restoreConsumedLayers(StockMovement $outMovement): void
    {
        $item = Item::find($outMovement->item_id);

        if ($item === null || $outMovement->movement_type === StockMovementType::IN) {
            return;
        }

        $candidates = StockMovement::query()
            ->where('branch_id', $outMovement->branch_id)
            ->where('item_id', $outMovement->item_id)
            ->where('warehouse_id', $outMovement->warehouse_id)
            ->where('movement_type', StockMovementType::IN->value)
            ->whereNotIn('status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            ->when(
                $outMovement->batch !== null && $outMovement->batch !== '',
                fn ($query) => $query->where('batch', $outMovement->batch),
            );

        $this->applyVariantFilter($candidates, $outMovement->variant_id);

        // Last emptied, first refilled.
        $layers = $candidates
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        if ($layers->isEmpty()) {
            return;
        }

        // Retired units still appear in a history, so these are looked up
        // withTrashed rather than through resolveConversionFactor's findOrFail.
        $itemUnit = (float) UnitMeasure::withTrashed()
            ->whereKey($item->unit_measure_id)
            ->value('unit') ?: 1.0;

        $units = UnitMeasure::withTrashed()
            ->whereIn('id', $layers->pluck('unit_measure_id')->push($outMovement->unit_measure_id)->unique())
            ->pluck('unit', 'id');

        $factor = fn (?string $unitMeasureId) => (float) ($units[$unitMeasureId] ?? $itemUnit) / $itemUnit ?: 1.0;

        $outFactor = $factor($outMovement->unit_measure_id);
        $remaining = (float) $outMovement->quantity * $outFactor;
        $issuedAt = (float) $outMovement->unit_cost / $outFactor;

        foreach ($layers as $layer) {
            if ($remaining <= self::QUANTITY_EPSILON) {
                break;
            }

            $layerFactor = $factor($layer->unit_measure_id);

            // Same cost means same layer: a FIFO row is priced at the layer it
            // came out of. Refilling a layer the issue never touched would
            // invent stock.
            if (abs(((float) $layer->unit_cost / $layerFactor) - $issuedAt) > 0.0001) {
                continue;
            }

            // qty_remaining is kept in the item's own unit; quantity is in
            // whichever unit the document used.
            $capacity = ((float) $layer->quantity * $layerFactor) - (float) $layer->qty_remaining;

            if ($capacity <= self::QUANTITY_EPSILON) {
                continue;
            }

            $giveBack = min($capacity, $remaining);
            $layer->qty_remaining = (float) $layer->qty_remaining + $giveBack;
            $layer->save();

            $remaining -= $giveBack;
        }
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
        $keys = [
            'branch_id' => $data['branch_id'],
            'item_id' => $data['item_id'],
            'warehouse_id' => $data['warehouse_id'],
            'variant_id' => $data['variant_id'],
            'batch' => $data['batch'] ?? null,
            'expire_date' => $data['expire_date'] ? $this->normalizeDate($data['expire_date']) : null,
        ];

        $balance = StockBalance::firstOrCreate(
            $keys,
            [
                'quantity' => 0,
                'status' => $data['status'] ?? StockStatus::DRAFT->value,
            ]
        );
        $newQty = $balance->quantity + $data['quantity'];

        // A reversal restores stock the business already owned — it is not a
        // new receipt at a market price, so it must not shift the average the
        // way a real purchase would. See TransactionService::voidStockMovementsFor().
        if (empty($data['skip_average_recost'])) {
            $totalQty = (float) StockBalance::where('item_id', $item->id)->sum('quantity');

            $newAvg = $this->calculateNewAverage(
                $totalQty,           // total qty before this receipt
                (float) $item->avg_cost,
                $data['quantity'],
                $data['unit_cost']
            );
            $item->avg_cost = $newAvg;
            $item->save();

            $this->replayVariantAverage($data['variant_id'] ?? null);
        }

        $balance->update([
            'quantity' => $newQty,
        ]);

    }

    /**
     * Take a cancelled receipt's cost back out of the average.
     *
     * increaseBalance() blends every receipt into item.avg_cost. Undoing that
     * receipt has to unblend it, or the average keeps the cost of goods the
     * business never ended up owning — which then mis-states the value of every
     * unit still on the shelf and every COGS figure taken from it afterwards.
     * A plain sale must not do this: an OUT never moved the average to begin
     * with, so only the compensating leg of a reversal asks for it.
     *
     * This is the exact inverse of calculateNewAverage(), fed the same
     * base-unit quantity and per-base-unit cost, and it must run before the
     * stock is deducted so the totals line up with the ones the receipt saw.
     */
    protected function unwindAverage(Item $item, array $balanceData): void
    {
        // Total before this deduction = total after the receipt being undone.
        $totalQty = (float) StockBalance::where('item_id', $item->id)->sum('quantity');
        $quantity = (float) $balanceData['quantity'];
        $remainingQty = $totalQty - $quantity;

        // Nothing left to carry an average. Leaving it as-is is harmless: the
        // next receipt onto an empty shelf sets the average outright rather
        // than blending (see calculateNewAverage's oldQty <= 0 branch).
        if ($remainingQty <= 0) {
            return;
        }

        $unwound = (($totalQty * (float) $item->avg_cost) - ($quantity * (float) $balanceData['unit_cost']))
            / $remainingQty;

        // A negative average is never meaningful; it would mean the receipt
        // carried more value than the shelf holds, which only happens if the
        // books drifted. Clamp rather than propagate the nonsense.
        $item->avg_cost = round(max(0.0, $unwound), 4);
        $item->save();
    }

    /**
     * Rebuild every variant's average from the movements it still holds.
     *
     * For the rebuild paths, which remove movements (an edited or deleted
     * purchase) rather than adding one.
     */
    public function recalculateVariantAverageCosts(string $itemId): void
    {
        $variantIds = ItemVariant::query()
            ->where('item_id', $itemId)
            ->pluck('id');

        foreach ($variantIds as $variantId) {
            $this->replayVariantAverage($variantId);
        }
    }

    /**
     * Public entry point for callers that move stock without re-pricing the
     * item — an item transfer is the same goods on a different shelf, so it
     * passes `skip_average_recost` and then asks for the variant figure to be
     * re-derived here rather than letting increaseBalance() blend the transfer
     * price into the item's average.
     */
    public function recalculateVariantAverage(?string $variantId): void
    {
        $this->replayVariantAverage($variantId);
    }

    /**
     * Public entry point for re-deriving the ITEM's average.
     *
     * The running figure kept by increaseBalance() cannot survive a reversal
     * that undoes an issue: the receipts that came after that issue blended
     * against a shelf the issue had already emptied, and no arithmetic undo
     * puts that back — the whole history has to be weighed again.
     */
    public function recalculateItemAverage(?string $itemId): void
    {
        $this->replayItemAverage($itemId);
    }

    /** Weigh every receipt the variant still holds, oldest first. */
    private function replayVariantAverage(?string $variantId): void
    {
        $variant = $variantId ? ItemVariant::find($variantId) : null;

        if ($variant === null) {
            return;
        }

        $average = $this->deriveAverage(
            StockMovement::query()->where('variant_id', $variantId),
            $variant->item?->unit_measure_id,
        );

        if ($average === null) {
            return;
        }

        $variant->avg_cost = round($average, 4);
        $variant->save();
    }

    /**
     * The item's own average, weighed over every bucket it holds.
     *
     * Deliberately identical in method to the variant replay above. When the
     * two disagree — the item saying 52.50 while its only variant says 51.67 —
     * the operator has no way to tell which figure prices their stock, and
     * whichever report reads the item is wrong.
     */
    private function replayItemAverage(?string $itemId): void
    {
        $item = $itemId ? Item::find($itemId) : null;

        if ($item === null) {
            return;
        }

        $average = $this->deriveAverage(
            StockMovement::query()->where('item_id', $itemId),
            $item->unit_measure_id,
        );

        if ($average === null) {
            return;
        }

        $item->avg_cost = round($average, 4);
        $item->save();
    }

    /**
     * Blend the receipts a query still returns, oldest first.
     *
     * Derived rather than kept running, because a running figure has to be
     * seeded and there is nothing trustworthy to seed it from: stock whose
     * earlier receipts predate this column sits at 0 with goods on the shelf,
     * and blending into that prices those goods at nothing — 15 pc carrying no
     * average plus 10 pc at 35,000 comes out at 14,000 rather than 34,400.
     * Deriving also means any figure that is already wrong is corrected by the
     * next receipt instead of compounding.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $movements  Already scoped to one item or one variant.
     * @return float|null  Null when nothing is left on the shelf to carry an average.
     */
    private function deriveAverage($movements, ?string $itemUnitMeasureId): ?float
    {
        $movements = $movements
            // A reversal voids both the original layer and the compensating one
            // it posts back. Replaying either would re-blend the cost of goods
            // the business never ended up owning.
            ->whereNotIn('status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            ->orderBy('date')
            ->orderBy('id')
            ->get(['movement_type', 'source', 'quantity', 'unit_cost', 'unit_measure_id']);

        // A movement records quantity and unit_cost in whichever unit its
        // document selected — 2 boxes at 60 for an item stocked in pieces.
        // Value survives that (2 x 60 = 12 x 10) but an average does not, so
        // each layer is converted to the item's own unit before it is blended.
        //
        // Looked up in one go rather than through resolveConversionFactor():
        // that findOrFail()s, and a history can name a unit that has since
        // been retired — which would turn an ordinary purchase into a crash.
        $itemUnit = (float) UnitMeasure::withTrashed()
            ->whereKey($itemUnitMeasureId)
            ->value('unit') ?: 1.0;

        $units = UnitMeasure::withTrashed()
            ->whereIn('id', $movements->pluck('unit_measure_id')->unique())
            ->pluck('unit', 'id');

        // Everything in the item's own unit, in the order it happened.
        $layers = $movements->map(function (StockMovement $movement) use ($units, $itemUnit) {
            $factor = (float) ($units[$movement->unit_measure_id] ?? $itemUnit) / $itemUnit;
            $factor = $factor > 0 ? $factor : 1.0;

            return (object) [
                'in' => $movement->movement_type === StockMovementType::IN,
                'source' => $movement->source,
                'quantity' => (float) $movement->quantity * $factor,
                'cost' => (float) $movement->unit_cost / $factor,
            ];
        })->all();

        $this->netPurchaseReturnsAgainstTheirReceipts($layers);

        $avgCost = 0.0;
        $runningQty = 0.0;

        foreach ($layers as $layer) {
            if ($layer->quantity <= 0) {
                continue;
            }

            if (! $layer->in) {
                $runningQty = max(0.0, $runningQty - $layer->quantity);

                continue;
            }

            if ($runningQty + $layer->quantity > 0) {
                $avgCost = (($runningQty * $avgCost) + ($layer->quantity * $layer->cost))
                    / ($runningQty + $layer->quantity);
            }

            $runningQty += $layer->quantity;
        }

        // An empty shelf keeps the last average it carried: the figure still
        // prices the returns and reversals that can arrive after the stock has
        // gone, and the next receipt derives it afresh anyway.
        return $runningQty > 0 ? $avgCost : null;
    }

    /**
     * Cancel each purchase return against the receipt it hands back.
     *
     * A purchase return is not an issue — nothing was consumed, the goods were
     * never really bought. Subtracting its value from the running blend instead
     * gives an answer that depends on where the return falls in the timeline,
     * because the blend it is undoing was struck against a different quantity
     * than the one standing when it is undone. Removing the units from the
     * receipt itself is order-independent and leaves the shelf holding exactly
     * what was kept.
     *
     * Receipts are matched on cost, which is sound because a return is posted
     * at the price its purchase paid (see consumeLayers). Anything that finds
     * no match — a return recorded before that was true — is left alone and
     * behaves as it always did, as a plain issue.
     *
     * @param  list<object>  $layers  Mutated in place.
     */
    private function netPurchaseReturnsAgainstTheirReceipts(array $layers): void
    {
        foreach ($layers as $return) {
            if ($return->in || $return->source !== StockSourceType::PURCHASE_RETURN) {
                continue;
            }

            foreach ($layers as $receipt) {
                if ($return->quantity <= self::QUANTITY_EPSILON) {
                    break;
                }

                if (! $receipt->in || $receipt->quantity <= 0) {
                    continue;
                }

                if (abs($receipt->cost - $return->cost) > 0.0001) {
                    continue;
                }

                $netted = min($receipt->quantity, $return->quantity);
                $receipt->quantity -= $netted;
                $return->quantity -= $netted;
            }
        }
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
                    ->tap(fn ($query) => $this->applyVariantFilter($query, $allocation['variant_id'] ?? null))
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
            ->tap(fn ($query) => $this->applyVariantFilter($query, $data['variant_id'] ?? null))
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
     * Constrain a stock balance / movement query to one variant.
     *
     * A NULL variant_id is its own distinct bucket, not a wildcard, so it must
     * match NULL exactly rather than matching everything — though in practice
     * every write now resolves a real variant_id (see resolveVariantId()), so
     * this only matters for rows written before that was true.
     */
    protected function applyVariantFilter($query, ?string $variantId)
    {
        return $query->when(
            $variantId !== null && $variantId !== '',
            fn ($q) => $q->where('variant_id', $variantId),
            fn ($q) => $q->whereNull('variant_id')
        );
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
        // Lock the matching balance rows so concurrent OUT posts serialize on the same
        // item/warehouse/batch. Postgres rejects FOR UPDATE with an aggregate, so we
        // fetch the locked rows and sum in PHP. The lock is held until this transaction
        // (StockService::post) commits, closing the check-to-deduct race window.
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
            ->tap(fn ($query) => $this->applyVariantFilter($query, $data['variant_id'] ?? null))
            ->lockForUpdate()
            ->get()
            ->sum(fn ($balance) => (float) $balance->quantity);

        if ($available + self::QUANTITY_EPSILON < (float) $data['quantity']) {
            // Named, translated and numbered: this message is the only thing
            // the operator gets when a document is refused, and "Insufficient
            // stock. item_id 01m3... available: 10 required: 999" told them
            // neither which item nor what to do about it.
            throw ValidationException::withMessages([
                'stock' => __('general.insufficient_stock_for_item', [
                    'item' => Item::find($data['item_id'])?->name ?? '',
                    'warehouse' => Warehouse::find($data['warehouse_id'])?->name ?? '',
                    'available' => rtrim(rtrim(number_format($available, 2, '.', ''), '0'), '.'),
                    'required' => rtrim(rtrim(number_format((float) $data['quantity'], 2, '.', ''), '0'), '.'),
                ]),
            ]);
        }
    }

    /**
     * The costing method for an item's stock-out valuation.
     *
     * The item's own `costing_method` wins; a blank one falls back to the
     * company default — see App\Models\Inventory\Item::effectiveCostingMethod().
     */
    protected function getCostingMethod(Item $item): string
    {
        return $item->effectiveCostingMethod()->value;
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

        if ($remaining > self::QUANTITY_EPSILON) {
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

        // skip_average_recost is read by increaseBalance() below; StockMovement
        // has no such column, so it is not stripped from $movementData here —
        // it is simply ignored by mass assignment like any other unknown key.

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

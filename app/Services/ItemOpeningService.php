<?php

namespace App\Services;

use App\Enums\CostingMethod;
use App\Enums\ItemType;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Editing an item's opening balances, and deciding which of them may still be
 * edited at all.
 *
 * An opening is a restatement of where the books started, so while nothing has
 * drawn on it there is no reason to freeze it. The moment stock has been issued
 * against it, the figure is load-bearing: a sale has already taken cost out of
 * that layer, the GL carries the matching COGS, and moving the opening
 * underneath would silently restate a closed period. Those are locked.
 *
 * "Used" is decided from the STOCK LAYER, not by asking each module (sales,
 * transfers, adjustments, purchases) whether it touched the item. Every issue in
 * the system goes out through StockService, so consumption is recorded in one
 * place — which means a module added later is covered without changing this
 * class.
 */
class ItemOpeningService
{
    /**
     * Matches StockService: persisted quantities are numeric(18,4), so a
     * shortfall below the storage resolution is not real consumption.
     */
    private const QUANTITY_EPSILON = 0.0001;

    public function __construct(
        private StockService $stock,
        private TransactionService $transactions,
        private DateConversionService $dates,
    ) {
    }

    // ==========================================================
    // LOCKING
    // ==========================================================

    /**
     * Why this opening can no longer be edited, or null while it is still free.
     *
     * Returns a translation key so the caller can show the same reason the API
     * enforced, rather than a generic refusal.
     */
    public function lockReason(StockMovement $opening): ?string
    {
        // FIFO and LIFO record consumption on the layer itself: deductFIFO()
        // draws qty_remaining down and flips the layer to POSTED. That is exact,
        // and it covers every issuing module at once — sale, transfer,
        // adjustment, or anything added later.
        //
        // Checked BEFORE the status below, because flipping to POSTED is how
        // FIFO records the issue: reading the status first would report the
        // mechanism ("already posted") instead of the reason the user needs
        // ("stock has been issued from this opening").
        $remaining = $opening->qty_remaining;

        if ($remaining !== null
            && (float) $remaining + self::QUANTITY_EPSILON < (float) $opening->quantity) {
            return 'item.opening_locked_issued';
        }

        // Weighted average never touches a layer — it decrements the balance and
        // costs the issue at the item's average. So under WAC the only evidence
        // that the opening was drawn on is an issue out of the same bucket.
        //
        // This check is deliberately NOT applied under FIFO/LIFO, where an issue
        // in the bucket may well have consumed a purchase layer and left the
        // opening untouched; qty_remaining above already answers that exactly.
        if (! $this->tracksLayers() && $this->hasIssuesInBucket($opening)) {
            return 'item.opening_locked_issued';
        }

        // Anything left that is not a live draft — voided, cancelled, or posted
        // without a matching draw-down — has still been through the ledger.
        if ($this->statusValue($opening->status) !== StockStatus::DRAFT->value) {
            return 'item.opening_locked_posted';
        }

        return null;
    }

    public function isLocked(StockMovement $opening): bool
    {
        return $this->lockReason($opening) !== null;
    }

    /**
     * Stamp is_locked / lock_reason onto each opening so the resource (and the
     * edit screen) can disable exactly the rows the update path will refuse.
     *
     * @param  Collection<int, StockMovement>  $openings
     */
    public function annotate(Collection $openings): Collection
    {
        return $openings->each(function (StockMovement $opening) {
            $reason = $this->lockReason($opening);

            $opening->setAttribute('is_locked', $reason !== null);
            $opening->setAttribute('lock_reason', $reason);
        });
    }

    /**
     * Whether the costing method records consumption against a specific layer.
     */
    private function tracksLayers(): bool
    {
        return in_array(
            BranchContext::costingMethod(),
            [CostingMethod::FIFO->value, CostingMethod::LIFO->value],
            true
        );
    }

    /**
     * Any issue out of this opening's exact bucket (warehouse + batch + expiry).
     */
    private function hasIssuesInBucket(StockMovement $opening): bool
    {
        return $this->bucketQuery(StockMovement::query(), $opening)
            ->where('movement_type', StockMovementType::OUT->value)
            ->exists();
    }

    // ==========================================================
    // SYNC
    // ==========================================================

    /**
     * Replace the item's editable openings with the submitted set.
     *
     * Locked rows are left exactly as they are and must come back unchanged;
     * anything else is rebuilt, because StockService has no "amend a layer"
     * path — the balance and the average cost were both derived from the
     * layer's quantity and cost, so the layer is reposted rather than patched.
     *
     * @param  array<int, mixed>  $rows
     */
    public function sync(Item $item, array $rows): void
    {
        DB::transaction(function () use ($item, $rows) {
            $existing = $item->openings()
                ->where('movement_type', StockMovementType::IN->value)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $locked = $existing->filter(fn (StockMovement $o) => $this->isLocked($o));
            $submitted = collect($rows)->filter(fn ($row) => is_array($row));

            $this->assertLockedRowsUntouched($locked, $submitted);

            // Nothing to do at all — leave the GL and the average cost alone
            // rather than churning a voucher on an unrelated item edit.
            if (! $this->hasChanges($existing, $locked, $submitted)) {
                return;
            }

            $existing->diffKeys($locked)->each(fn (StockMovement $o) => $this->removeOpening($o));

            $submitted
                ->reject(fn ($row) => filled($row['id'] ?? null) && $locked->has($row['id']))
                ->filter(fn ($row) => $this->isUsableRow($row))
                ->each(fn ($row) => $this->postOpening($item, $row));

            $this->rebuildOpeningVoucher($item);
            $this->refreshAverageCost($item);
        });
    }

    /**
     * A locked opening may be neither edited nor dropped.
     *
     * The edit screen disables these fields, so a mismatch here is either a
     * stale page or a hand-rolled request — both of which must be refused
     * rather than quietly ignored, or the user is told "saved" while their
     * change was discarded.
     *
     * @param  Collection<string, StockMovement>  $locked
     * @param  Collection<int, array<string, mixed>>  $submitted
     */
    private function assertLockedRowsUntouched(Collection $locked, Collection $submitted): void
    {
        if ($locked->isEmpty()) {
            return;
        }

        $byId = $submitted->filter(fn ($row) => filled($row['id'] ?? null))->keyBy('id');

        foreach ($locked as $id => $opening) {
            $row = $byId->get($id);

            if ($row === null || $this->differsFrom($opening, $row)) {
                throw ValidationException::withMessages([
                    'openings' => __('general.opening_locked_cannot_update'),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function differsFrom(StockMovement $opening, array $row): bool
    {
        if ($this->differsNumerically($opening->quantity, $row['quantity'] ?? null)
            || $this->differsNumerically($opening->unit_cost, $row['unit_price'] ?? null)) {
            return true;
        }

        if ((string) $opening->warehouse_id !== (string) ($row['warehouse_id'] ?? '')) {
            return true;
        }

        if ((string) ($opening->batch ?? '') !== (string) ($row['batch'] ?? '')) {
            return true;
        }

        return $this->expiryDiffers($opening, $row['expire_date'] ?? null);
    }

    private function differsNumerically(mixed $stored, mixed $submitted): bool
    {
        return abs((float) $stored - (float) $submitted) > self::QUANTITY_EPSILON;
    }

    /**
     * The form round-trips expiry in the user's calendar (Jalali for most of
     * these companies), so both sides are normalised to Gregorian before being
     * compared. A value that will not convert is treated as unchanged rather
     * than blocking a save on a formatting difference.
     */
    private function expiryDiffers(StockMovement $opening, mixed $submitted): bool
    {
        $stored = $opening->expire_date?->toDateString();

        try {
            $incoming = filled($submitted) ? $this->dates->toGregorian((string) $submitted) : null;
        } catch (\Throwable) {
            return false;
        }

        return (string) $stored !== (string) $incoming;
    }

    /**
     * Is any editable opening actually different from what is stored?
     *
     * @param  Collection<string, StockMovement>  $existing
     * @param  Collection<string, StockMovement>  $locked
     * @param  Collection<int, array<string, mixed>>  $submitted
     */
    private function hasChanges(Collection $existing, Collection $locked, Collection $submitted): bool
    {
        $editable = $existing->diffKeys($locked);
        $incoming = $submitted
            ->reject(fn ($row) => filled($row['id'] ?? null) && $locked->has($row['id']))
            ->filter(fn ($row) => $this->isUsableRow($row));

        if ($editable->count() !== $incoming->count()) {
            return true;
        }

        $unmatched = $editable;

        foreach ($incoming as $row) {
            $match = $unmatched->first(fn (StockMovement $o) => ! $this->differsFrom($o, $row));

            if ($match === null) {
                return true;
            }

            $unmatched = $unmatched->forget($match->getKey());
        }

        return $unmatched->isNotEmpty();
    }

    /**
     * Rows the user actually filled in — the form always renders a blank row.
     *
     * @param  array<string, mixed>  $row
     */
    private function isUsableRow(array $row): bool
    {
        return filled($row['warehouse_id'] ?? null) && (float) ($row['quantity'] ?? 0) > 0;
    }

    // ==========================================================
    // STOCK SIDE
    // ==========================================================

    /**
     * Take an editable opening back out of stock.
     *
     * The balance bucket is DECREMENTED, not deleted: purchases and adjustments
     * land in the same bucket, and dropping the row would take their quantity
     * with it. A bucket that empties is removed, since StockService rebuilds it
     * with firstOrCreate on the next receipt anyway.
     */
    private function removeOpening(StockMovement $opening): void
    {
        $balances = $this->bucketQuery(StockBalance::query(), $opening)
            ->lockForUpdate()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Openings are always posted in the item's own unit, so the layer
        // quantity and the balance quantity are 1:1.
        $remaining = (float) $opening->quantity;

        foreach ($balances as $balance) {
            if ($remaining <= 0) {
                break;
            }

            $deduct = min((float) $balance->quantity, $remaining);
            $newQuantity = round((float) $balance->quantity - $deduct, 4);
            $remaining -= $deduct;

            $newQuantity <= 0
                ? $balance->forceDelete()
                : $balance->update(['quantity' => $newQuantity]);
        }

        $opening->forceDelete();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function postOpening(Item $item, array $row): void
    {
        $this->stock->post([
            'item_id'         => $item->id,
            'movement_type'   => StockMovementType::IN->value,
            'unit_measure_id' => $item->unit_measure_id,
            'quantity'        => (float) $row['quantity'],
            'source'          => StockSourceType::OPENING->value,
            'unit_cost'       => (float) ($row['unit_price'] ?? 0),
            'status'          => StockStatus::DRAFT->value,
            'batch'           => $row['batch'] ?? null,
            'date'            => Carbon::now()->toDateString(),
            'expire_date'     => $row['expire_date'] ?? null,
            'size_id'         => $item->size_id,
            'warehouse_id'    => $row['warehouse_id'],
            'branch_id'       => $item->branch_id ?? auth()->user()?->branch_id ?? app('active_branch_id'),
        ]);
    }

    /**
     * Narrow a stock_movements / stock_balances query to one opening's bucket.
     *
     * Batch and expiry are matched with an explicit IS NULL on the empty side —
     * `where('batch', null)` never matches in SQL, which would silently widen
     * the bucket to every batch of the item.
     */
    private function bucketQuery($query, StockMovement $opening)
    {
        return $query
            ->where('branch_id', $opening->branch_id)
            ->where('item_id', $opening->item_id)
            ->where('warehouse_id', $opening->warehouse_id)
            ->when(
                filled($opening->batch),
                fn ($q) => $q->where('batch', $opening->batch),
                fn ($q) => $q->whereNull('batch')
            )
            ->when(
                $opening->expire_date !== null,
                fn ($q) => $q->whereDate('expire_date', $opening->expire_date->toDateString()),
                fn ($q) => $q->whereNull('expire_date')
            );
    }

    /**
     * Rebuild avg_cost from the layers that survive.
     *
     * StockService keeps a running average as receipts arrive, which cannot be
     * unwound when a layer is pulled out from the middle of the history. A
     * weighted average over the remaining receipts is the same figure the
     * running one would have reached had the removed opening never existed.
     */
    private function refreshAverageCost(Item $item): void
    {
        $totals = StockMovement::query()
            ->where('item_id', $item->id)
            ->where('movement_type', StockMovementType::IN->value)
            ->selectRaw('COALESCE(SUM(quantity), 0) as qty, COALESCE(SUM(quantity * unit_cost), 0) as value')
            ->first();

        $quantity = (float) ($totals->qty ?? 0);

        $item->forceFill([
            'avg_cost' => $quantity > 0 ? round((float) $totals->value / $quantity, 4) : 0,
        ])->save();
    }

    // ==========================================================
    // GL SIDE
    // ==========================================================

    /**
     * Re-post the item's opening voucher for the value now on hand as opening.
     *
     * The voucher is rebuilt rather than adjusted by a delta because store()
     * produces exactly one voucher carrying the total of every opening, and the
     * two paths must leave the GL in the same shape. Locked openings keep their
     * exact quantity and cost, so their share of the total is unchanged and the
     * rebuild only ever moves the editable part.
     */
    private function rebuildOpeningVoucher(Item $item): void
    {
        $existingIds = Transaction::query()
            ->whereIn('reference_type', ['item', Item::class])
            ->where('reference_id', $item->id)
            ->pluck('id');

        if ($existingIds->isNotEmpty()) {
            TransactionLine::whereIn('transaction_id', $existingIds)->forceDelete();
            Transaction::whereIn('id', $existingIds)->forceDelete();
        }

        $value = (float) $item->openings()
            ->where('movement_type', StockMovementType::IN->value)
            ->sum(DB::raw('quantity * unit_cost'));

        if ($value <= 0) {
            return;
        }

        $homeCurrency = BranchContext::homeCurrency();
        $equityId = BranchContext::glAccount('opening-balance-equity');
        $inventoryId = $this->inventoryAccount($item);

        if ($homeCurrency === null || $equityId === null || $inventoryId === null) {
            throw new RuntimeException(
                'System accounts (inventory or opening balance equity) or the base currency are missing.'
            );
        }

        $remarks = [
            'remark' => 'Opening balance for item ' . $item->name,
            'remark_fa' => 'موجودی اولیه برای جنس ' . ' ' . $item->name,
            'remark_ps' => 'د' . ' ' . $item->name . ' ' . 'د پرانیستلو بیلانس ',
        ];

        $this->transactions->post(
            header: [
                'currency_id' => $homeCurrency->id,
                'rate' => 1,
                'voucher_number' => 'Opening Balance ' . $item->name . ' #' . $item->code,
                'date' => Carbon::now()->toDateString(),
                'reference_type' => Item::class,
                'reference_id' => $item->id,
                'remark' => $remarks['remark'],
            ],
            lines: [
                ['account_id' => $inventoryId, 'debit' => $value, 'credit' => 0] + $remarks,
                ['account_id' => $equityId, 'debit' => 0, 'credit' => $value] + $remarks,
            ],
        );
    }

    /**
     * The item's own asset account wins; the item-type default is the fallback,
     * matching how store() picks the debit side.
     */
    private function inventoryAccount(Item $item): ?string
    {
        if (filled($item->asset_account_id)) {
            return $item->asset_account_id;
        }

        return BranchContext::glAccount(match ($this->itemTypeValue($item)) {
            ItemType::NON_INVENTORY_MATERIALS->value => 'non-inventory-items',
            ItemType::RAW_MATERIALS->value => 'raw-materials',
            ItemType::FINISHED_GOOD_ITEMS->value => 'finished-goods',
            default => 'inventory-stock',
        });
    }

    private function itemTypeValue(Item $item): ?string
    {
        return $item->item_type instanceof ItemType
            ? $item->item_type->value
            : ($item->item_type === null ? null : (string) $item->item_type);
    }

    private function statusValue(mixed $status): string
    {
        return $status instanceof StockStatus ? $status->value : (string) $status;
    }
}

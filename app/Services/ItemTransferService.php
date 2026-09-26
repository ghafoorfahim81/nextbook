<?php

namespace App\Services;

use App\Http\Controllers\Concerns\ResolvesLineVariant;
use App\Enums\TransactionStatus;
use App\Models\Account\Account;
use App\Models\ItemTransfer\ItemTransfer;
use App\Models\ItemTransfer\ItemTransferItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;
use App\Models\Inventory\StockMovement;
use App\Models\Transaction\Transaction;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Services\DateConversionService;
use App\Support\BranchContext;

class ItemTransferService
{
    use ResolvesLineVariant;

    public function __construct(
        private StockService $stockService,
        private ActivityLogService $activityLogService,
        private DateConversionService $dateConversionService,
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
                'status' => TransactionStatus::DRAFT,
                'remarks' => $data['remarks'] ?? null,
                ...$this->transferCostAttributes($data),
            ]);

            // Create transfer items
            foreach ($data['items'] as $itemData) {
                ItemTransferItem::create([
                    'item_transfer_id' => $transfer->id,
                    'item_id' => $itemData['item_id'],
                    'variant_id' => $this->resolveLineVariantId($itemData),
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
            if ($transfer->status === TransactionStatus::POSTED) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot update a completed transfer.'],
                ]);
            }

            // If transfer was cancelled and we're reactivating, validate stock
            if ($transfer->status === TransactionStatus::REVERSED && isset($data['status']) && $data['status'] === TransactionStatus::DRAFT->value) {
                $this->validateStockAvailability($data['items'] ?? $transfer->items->toArray(), $data['from_warehouse_id'] ?? $transfer->from_warehouse_id);
            }

            // Update transfer record
            $transfer->update([
                'date' => $this->dateConversionService->toGregorian($data['date']) ?? $transfer->date,
                'from_warehouse_id' => $data['from_warehouse_id'] ?? $transfer->from_warehouse_id,
                'to_warehouse_id' => $data['to_warehouse_id'] ?? $transfer->to_warehouse_id,
                'status' => $data['status'] ?? $transfer->status,
                'remarks' => $data['remarks'] ?? $transfer->remarks,
                ...$this->transferCostAttributes($data, $transfer),
            ]);

            // Update items if provided
            if (isset($data['items'])) {
                $transfer->items()->delete();
                foreach ($data['items'] as $itemData) {
                    ItemTransferItem::create([
                        'item_transfer_id' => $transfer->id,
                        'item_id' => $itemData['item_id'],
                        'variant_id' => $this->resolveLineVariantId($itemData),
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
            if ($transfer->status === TransactionStatus::POSTED) {
                throw ValidationException::withMessages([
                    'status' => ['Transfer is already completed.'],
                ]);
            }

            if ($transfer->status === TransactionStatus::REVERSED) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot complete a cancelled transfer.'],
                ]);
            }

            // Validate stock availability
            $this->validateStockAvailability($transfer->items->toArray(), $transfer->from_warehouse_id);

            $this->postTransferCost($transfer);

            $date = $this->dateConversionService->toGregorian($transfer->date);
            $movedVariantIds = [];

            // Process each item
            foreach ($transfer->items as $item) {
                // Both legs carry the SAME cost and neither re-prices the item:
                // a transfer is the same goods on a different shelf, so blending
                // the line price into item.avg_cost would invent a purchase that
                // never happened. `unit_cost_override` pins the layer's cost and
                // `skip_average_recost` keeps the item average where it was; the
                // variant figure is re-derived from history once both legs exist.
                $unitCost = (float) $item->unit_price;

                $leg = [
                    'item_id'             => $item->item_id,
                    'unit_measure_id'     => $item->measure_id, // from item form
                    'quantity'            => (float) $item->quantity,
                    'source'              => StockSourceType::ITEM_TRANSFER->value,
                    'unit_cost'           => $unitCost,
                    'unit_cost_override'  => $unitCost,
                    // The document is being posted, so its stock is posted too. A
                    // draft movement here would leave the item's In/Out history
                    // showing an already-final document as unposted.
                    'status'              => StockStatus::POSTED->value,
                    'skip_average_recost' => true,
                    'batch'               => $item->batch ?? null,
                    'date'                => $date,
                    'expire_date'         => $item->expire_date ?? null,
                    'variant_id'          => $item->variant_id ?? null,
                    'branch_id'           => $transfer->branch_id,
                    'reference_type'      => ItemTransfer::class,
                    'reference_id'        => $transfer->id,
                ];

                // Remove stock from the source warehouse...
                $this->stockService->post([
                    ...$leg,
                    'movement_type' => StockMovementType::OUT->value,
                    'warehouse_id'  => $transfer->from_warehouse_id,
                ]);

                // ...and put it into the destination warehouse.
                [$inMovement] = $this->stockService->post([
                    ...$leg,
                    'movement_type' => StockMovementType::IN->value,
                    'warehouse_id'  => $transfer->to_warehouse_id,
                ]);

                // Read the variant back off the movement rather than off the
                // line: a line that names no variant still lands in the item's
                // default one, and that is the average that has to be re-derived.
                if ($inMovement?->variant_id) {
                    $movedVariantIds[$inMovement->variant_id] = $inMovement->variant_id;
                }
            }

            // Re-derive each moved variant's average from its own history, now
            // that both legs are on record.
            foreach ($movedVariantIds as $variantId) {
                $this->stockService->recalculateVariantAverage($variantId);
            }

            // Update transfer status
            $oldStatus = $transfer->status?->value ?? $transfer->status;
            $transfer->update(['status' => TransactionStatus::POSTED]);

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
            if ($transfer->status === TransactionStatus::REVERSED) {
                throw ValidationException::withMessages([
                    'status' => ['Transfer is already cancelled.'],
                ]);
            }

            // If transfer was completed, revert stock changes
            if ($transfer->status === TransactionStatus::POSTED) {

                $movements = StockMovement::query()
                    ->where('reference_type', ItemTransfer::class)
                    ->where('reference_id', $transfer->id)
                    ->whereNotIn('status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
                    ->get();

                $movedVariantIds = [];

                foreach ($movements as $movement) {
                    // Both the original layer and the one that undoes it are
                    // marked voided, the way TransactionService does it: an
                    // undone movement is not history the item's In/Out lists,
                    // totals or costing should keep counting.
                    $this->stockService->post([
                        'item_id' => $movement->item_id,
                        'movement_type' => $movement->movement_type === StockMovementType::IN
                            ? StockMovementType::OUT->value
                            : StockMovementType::IN->value,
                        'unit_measure_id' => $movement->unit_measure_id,
                        'quantity' => (float) $movement->quantity,
                        'source' => StockSourceType::ITEM_TRANSFER->value,
                        'unit_cost' => (float) $movement->unit_cost,
                        'unit_cost_override' => (float) $movement->unit_cost,
                        'status' => StockStatus::VOIDED->value,
                        // Putting the goods back where they were is not a
                        // purchase, so it must not shift any average.
                        'skip_average_recost' => true,
                        'batch' => $movement->batch,
                        'date' => now()->toDateString(),
                        'expire_date' => $movement->expire_date,
                        'variant_id' => $movement->variant_id,
                        'warehouse_id' => $movement->warehouse_id,
                        'branch_id' => $transfer->branch_id,
                        'reference_type' => ItemTransfer::class,
                        'reference_id' => $transfer->id,
                    ]);

                    // The OUT leg emptied layers in the source warehouse. The
                    // compensating IN above puts the quantity back on the shelf
                    // but writes a voided row, which is no use as a layer — so
                    // the layers it consumed are refilled directly, or the
                    // source warehouse ends up holding stock that FIFO cannot
                    // see. (The IN leg needs nothing: its own layer is voided
                    // below, which retires it.)
                    if ($movement->movement_type === StockMovementType::OUT) {
                        $this->stockService->restoreConsumedLayers($movement);
                    }

                    if ($movement->variant_id) {
                        $movedVariantIds[$movement->variant_id] = $movement->variant_id;
                    }
                }

                foreach ($movements as $movement) {
                    $movement->update(['status' => StockStatus::VOIDED->value]);
                }

                foreach ($movedVariantIds as $variantId) {
                    $this->stockService->recalculateVariantAverage($variantId);
                }

                // The freight voucher is reversed LAST, on purpose.
                // TransactionService::reverse() also undoes the stock behind the
                // document it reverses, and its generic rules (unwind the
                // average a receipt blended in) are wrong for an internal move
                // that never blended one. Voiding the legs above first leaves it
                // nothing to compensate, so the GL is reversed and the stock is
                // not undone twice.
                $transaction = Transaction::query()
                    ->where('reference_type', ItemTransfer::class)
                    ->where('reference_id', $transfer->id)
                    ->where('status', TransactionStatus::POSTED->value)
                    ->first();

                if ($transaction) {
                    // Naming the document and its type is what makes the
                    // reversal remark come back translated: TransactionService
                    // keeps the per-language wording keyed by reference type.
                    $route = $this->transferRouteLabel($transfer);

                    app(TransactionService::class)->reverse(
                        $transaction,
                        'Reversal of item transfer ' . $route,
                        $route,
                        ItemTransfer::class,
                    );
                }
            }

            // Update transfer status
            $oldStatus = $transfer->status?->value ?? $transfer->status;
            $transfer->update(['status' => TransactionStatus::REVERSED]);

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
     * The freight half of a transfer, normalised from the form payload.
     *
     * The switch is the single source of truth: turning it off clears the
     * amount and the accounts rather than leaving a stale cost behind that the
     * next post would pick up. The expense side defaults to the dedicated
     * "Item Transfer Expense" account so the P&L shows what moving stock costs
     * on its own line instead of inside Other Expenses.
     *
     * @return array<string, mixed>
     */
    private function transferCostAttributes(array $data, ?ItemTransfer $current = null): array
    {
        // An update that does not mention the switch at all is not a request to
        // turn it off. Every rule on it is `sometimes`, so a partial payload
        // must leave what is already stored alone.
        if ($current && ! array_key_exists('has_transfer_cost', $data)) {
            return [];
        }

        $hasCost = filter_var($data['has_transfer_cost'] ?? false, FILTER_VALIDATE_BOOL);

        if (! $hasCost) {
            return [
                'has_transfer_cost' => false,
                'transfer_cost' => null,
                'bank_account_id' => null,
                'expense_account_id' => null,
                'currency_id' => null,
                'rate' => null,
            ];
        }

        return [
            'has_transfer_cost' => true,
            'transfer_cost' => $data['transfer_cost'] ?? null,
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'expense_account_id' => $data['expense_account_id']
                ?? BranchContext::glAccount('item-transfer-expense'),
            'currency_id' => $data['currency_id'] ?? BranchContext::homeCurrency()?->id,
            'rate' => $data['rate'] ?? 1,
        ];
    }

    /**
     * Post the freight voucher for a transfer that carries one.
     *
     * The cost is paid out of the named bank/cash account and charged to the
     * transfer expense account. Both lines stay in the document's currency —
     * TransactionService converts them with the header rate — and nothing is
     * written when the switch is off or the amount is zero.
     */
    private function postTransferCost(ItemTransfer $transfer): void
    {
        $amount = (float) ($transfer->transfer_cost ?? 0);

        if (! $transfer->has_transfer_cost || $amount <= 0) {
            return;
        }

        $glAccounts = BranchContext::glAccounts();
        $homeCurrency = BranchContext::homeCurrency();

        $bankAccountId = $transfer->bank_account_id ?? $glAccounts['cash-in-hand'] ?? null;
        $expenseAccountId = $transfer->expense_account_id
            ?? $glAccounts['item-transfer-expense']
            ?? $glAccounts['other-expenses']
            ?? null;

        if (! $bankAccountId || ! $expenseAccountId) {
            throw ValidationException::withMessages([
                'transfer_cost' => [__('general.transfer_cost_accounts_missing')],
            ]);
        }

        // The ledger reads back in the operator's language, and it names the
        // route the goods took — a bare ULID told nobody anything.
        $route = $this->transferRouteLabel($transfer);
        $bankName = Account::find($bankAccountId)?->name ?? '';
        $bankLocalName = Account::find($bankAccountId)?->local_name ?: $bankName;

        // An operator's own note wins over the generated wording, in every
        // language: it is their sentence, not ours to translate.
        $own = trim((string) $transfer->remarks);

        $expenseRemark = $own !== '' ? [$own, $own, $own] : [
            'Transfer cost for item transfer ' . $route,
            'هزینه انتقال جنس ' . $route,
            'د جنس د لیږد لګښت ' . $route,
        ];

        $bankRemark = $own !== '' ? [$own, $own, $own] : [
            'Transfer cost paid from ' . $bankName . ' for item transfer ' . $route,
            'پرداخت هزینه انتقال جنس از حساب ' . $bankLocalName . ' ' . $route,
            'د جنس د لیږد لګښت تادیه له حساب ' . $bankLocalName . ' ' . $route,
        ];

        app(TransactionService::class)->post(
            header: [
                'currency_id' => $transfer->currency_id ?? $homeCurrency?->id,
                'rate' => (float) ($transfer->rate ?? 1) ?: 1,
                'date' => $this->dateConversionService->toGregorian($transfer->date),
                // The header has no per-language columns, so it carries the
                // English wording; the lines below are what the ledger shows.
                'remark' => 'Transfer cost for item transfer ' . $route,
                'reference_type' => ItemTransfer::class,
                'reference_id' => $transfer->id,
                'status' => TransactionStatus::POSTED->value,
                'branch_id' => $transfer->branch_id,
            ],
            lines: [
                [
                    'account_id' => $bankAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'remark' => $bankRemark[0],
                    'remark_fa' => $bankRemark[1],
                    'remark_ps' => $bankRemark[2],
                ],
                [
                    'account_id' => $expenseAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'remark' => $expenseRemark[0],
                    'remark_fa' => $expenseRemark[1],
                    'remark_ps' => $expenseRemark[2],
                ],
            ],
        );
    }

    /**
     * How a transfer is named in a remark: the route, not the primary key.
     *
     * Warehouse names are stored once (there is no local_name on warehouses),
     * so the same label works in all three languages, and the date pins it to
     * one document without exposing a 26-character ULID to the operator.
     */
    private function transferRouteLabel(ItemTransfer $transfer): string
    {
        $transfer->loadMissing(['fromWarehouse', 'toWarehouse']);

        $from = $transfer->fromWarehouse?->name ?? '';
        $to = $transfer->toWarehouse?->name ?? '';
        $date = $this->dateConversionService->toDisplay($transfer->date);

        return trim(sprintf('%s → %s (%s)', $from, $to, $date));
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

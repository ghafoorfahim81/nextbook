<?php

namespace App\Services;

use App\Http\Controllers\Concerns\ResolvesLineVariant;
use App\Enums\TransactionStatus;
use App\Models\Account\Account;
use App\Models\Administration\Warehouse;
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
use App\Support\CompanyContext;

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

            // Both levels come off the source warehouse rather than the
            // request context: a transfer always belongs to the books the stock
            // is leaving, including when it is created from a job or a command.
            $source = Warehouse::withoutGlobalScopes()
                ->whereKey($data['from_warehouse_id'])
                ->first(['company_id', 'branch_id']);

            // Create transfer record
            $transfer = ItemTransfer::create([
                'company_id' => $source?->company_id,
                'branch_id' => $source?->branch_id,
                // Phase 4: a different branch here makes it a send-and-receive.
                'to_branch_id' => $this->branchOfWarehouse($data['to_warehouse_id']),
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
                    'status' => [__('messages.transfer.cannot_update_completed')],
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
                'to_branch_id' => $this->branchOfWarehouse($data['to_warehouse_id'] ?? $transfer->to_warehouse_id),
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
                    'status' => [__('messages.transfer.already_completed')],
                ]);
            }

            if ($transfer->status === TransactionStatus::REVERSED) {
                throw ValidationException::withMessages([
                    'status' => [__('messages.transfer.cannot_complete_cancelled')],
                ]);
            }

            // Validate stock availability
            $this->validateStockAvailability($transfer->items->toArray(), $transfer->from_warehouse_id);

            $this->postTransferCost($transfer);

            $date = $this->dateConversionService->toGregorian($transfer->date);
            $movedVariantIds = [];
            // Phase 4: to another branch, only the leg out happens now; the
            // receiving branch books the goods in later (receiveTransfer).
            $interbranch = $this->isInterbranch($transfer);

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
                    'reference_type'      => ItemTransfer::class,
                    'reference_id'        => $transfer->id,
                ];

                // Remove stock from the source warehouse...
                $this->stockService->post([
                    ...$leg,
                    'movement_type' => StockMovementType::OUT->value,
                    'warehouse_id'  => $transfer->from_warehouse_id,
                ]);

                if ($interbranch) {
                    continue;
                }

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

            if ($interbranch) {
                // The goods left the source branch's books for Goods in Transit.
                $this->postTransit($transfer, $transfer->items->mapWithKeys(fn ($item) => [$item->id => (float) $item->quantity])->all(), receiving: false);
            } else {
                // One step: received the moment it is sent.
                $transfer->items->each(fn ($item) => $item->update(['received_quantity' => $item->quantity]));
            }

            // Update transfer status
            $oldStatus = $transfer->status?->value ?? $transfer->status;
            $transfer->update([
                'status' => TransactionStatus::POSTED,
                'received_at' => $interbranch ? null : now(),
                'received_by' => $interbranch ? null : auth()->id(),
            ]);

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
     * Receive a transfer sent from another branch (phase 4).
     *
     * Done in the receiving branch, by someone working there — the sender does
     * not confirm their own delivery. Each line may arrive short: the goods
     * that came are booked into the destination warehouse at the cost they
     * left with, and the shortfall is charged to inventory shrinkage.
     *
     * @param  array<string, float|int|string>  $received  line id => quantity
     *                                                     that arrived (a line
     *                                                     left out arrived in full)
     */
    public function receiveTransfer(ItemTransfer $transfer, array $received = []): ItemTransfer
    {
        return DB::transaction(function () use ($transfer, $received) {
            $transfer->refresh()->load('items');

            if (! $this->isInterbranch($transfer)
                || $transfer->status !== TransactionStatus::POSTED
                || $transfer->received_at !== null) {
                throw ValidationException::withMessages([
                    'status' => [__('messages.transfer.not_in_transit')],
                ]);
            }

            if (CompanyContext::branchId() !== (string) $transfer->to_branch_id) {
                throw ValidationException::withMessages([
                    'status' => [__('messages.transfer.receive_in_destination')],
                ]);
            }

            $quantities = [];
            foreach ($transfer->items as $item) {
                $sent = (float) $item->quantity;
                $arrived = array_key_exists($item->id, $received) ? (float) $received[$item->id] : $sent;

                if ($arrived < 0 || $arrived > $sent) {
                    throw ValidationException::withMessages([
                        "received.{$item->id}" => [__('messages.transfer.received_more_than_sent')],
                    ]);
                }

                $quantities[$item->id] = $arrived;
            }

            $movedVariantIds = [];
            foreach ($transfer->items as $item) {
                $arrived = $quantities[$item->id];
                $item->update(['received_quantity' => $arrived]);

                if ($arrived <= 0) {
                    continue;
                }

                $unitCost = (float) $item->unit_price;
                [$inMovement] = $this->stockService->post([
                    'item_id' => $item->item_id,
                    'unit_measure_id' => $item->measure_id,
                    'quantity' => $arrived,
                    'source' => StockSourceType::ITEM_TRANSFER->value,
                    // The cost the goods left with: arriving is not a purchase.
                    'unit_cost' => $unitCost,
                    'unit_cost_override' => $unitCost,
                    'status' => StockStatus::POSTED->value,
                    'skip_average_recost' => true,
                    'batch' => $item->batch ?? null,
                    'date' => now()->toDateString(),
                    'expire_date' => $item->expire_date ?? null,
                    'variant_id' => $item->variant_id ?? null,
                    'reference_type' => ItemTransfer::class,
                    'reference_id' => $transfer->id,
                    'movement_type' => StockMovementType::IN->value,
                    'warehouse_id' => $transfer->to_warehouse_id,
                ]);

                if ($inMovement?->variant_id) {
                    $movedVariantIds[$inMovement->variant_id] = $inMovement->variant_id;
                }
            }

            foreach ($movedVariantIds as $variantId) {
                $this->stockService->recalculateVariantAverage($variantId);
            }

            $this->postTransit($transfer, $quantities, receiving: true);

            $transfer->update(['received_at' => now(), 'received_by' => auth()->id()]);

            $this->activityLogService->logAction(
                eventType: 'received',
                reference: $transfer,
                module: 'item_transfer',
                description: "Item transfer #{$transfer->id} received.",
                newValues: ['received' => $quantities],
                metadata: ['action' => 'item_transfer_receive'],
            );

            return $transfer->load('items');
        });
    }

    /**
     * The ledger side of a transfer between branches (phase 4).
     *
     * Sending: Goods in Transit is debited and each item's inventory account
     * credited, in the sending branch. Receiving: inventory is debited for
     * what arrived and shrinkage for what did not, Goods in Transit credited in
     * full, in the receiving branch — so the two branches' books each show
     * their side and Goods in Transit clears once the goods are in.
     *
     * @param  array<string, float>  $quantities  line id => quantity (sent, or arrived)
     */
    private function postTransit(ItemTransfer $transfer, array $quantities, bool $receiving): void
    {
        $gl = CompanyContext::glAccounts((string) $transfer->company_id);
        $transitId = $gl['goods-in-transit'] ?? null;
        $shrinkageId = $gl['inventory-shrinkage-and-wastage'] ?? null;

        if (! $transitId || ($receiving && ! $shrinkageId)) {
            throw ValidationException::withMessages([
                'status' => [__('messages.transfer.transit_accounts_missing')],
            ]);
        }

        $assetAccounts = \App\Models\Inventory\Item::withoutGlobalScopes()
            ->whereIn('id', $transfer->items->pluck('item_id'))
            ->pluck('asset_account_id', 'id');

        $debits = [];
        $credits = [];
        $add = function (array &$side, ?string $account, float $amount): void {
            if ($account && $amount > 0) {
                $side[$account] = ($side[$account] ?? 0) + $amount;
            }
        };

        foreach ($transfer->items as $item) {
            $price = (float) $item->unit_price;
            $sent = round((float) $item->quantity * $price, 4);
            $inventoryId = $assetAccounts[$item->item_id] ?? $gl['inventory-stock'] ?? null;

            if (! $receiving) {
                $add($debits, $transitId, $sent);
                $add($credits, $inventoryId, $sent);
                continue;
            }

            $arrived = round((float) ($quantities[$item->id] ?? $item->quantity) * $price, 4);
            $add($debits, $inventoryId, $arrived);
            $add($debits, $shrinkageId, round($sent - $arrived, 4));
            $add($credits, $transitId, $sent);
        }

        if (array_sum($debits) <= 0) {
            // Nothing priced, nothing to move in the ledger.
            return;
        }

        $route = $this->transferRouteLabel($transfer);
        $remark = $receiving
            ? ['Received item transfer ' . $route, 'دریافت انتقال جنس ' . $route, 'د جنس د لیږد ترلاسه کول ' . $route]
            : ['Item transfer sent ' . $route, 'ارسال انتقال جنس ' . $route, 'د جنس لیږل ' . $route];

        $lines = [];
        foreach (['debit' => $debits, 'credit' => $credits] as $side => $amounts) {
            foreach ($amounts as $accountId => $amount) {
                $lines[] = [
                    'account_id' => $accountId,
                    'debit' => $side === 'debit' ? $amount : 0,
                    'credit' => $side === 'credit' ? $amount : 0,
                    'remark' => $remark[0],
                    'remark_fa' => $remark[1],
                    'remark_ps' => $remark[2],
                ];
            }
        }

        app(TransactionService::class)->post(
            header: [
                'currency_id' => CompanyContext::homeCurrency((string) $transfer->company_id)?->id,
                'rate' => 1,
                'date' => $receiving ? now()->toDateString() : $this->dateConversionService->toGregorian($transfer->date),
                'remark' => $remark[0],
                'reference_type' => ItemTransfer::class,
                'reference_id' => $transfer->id,
                'status' => TransactionStatus::POSTED->value,
                // Each branch's books carry its own side of the move.
                'branch_id' => $receiving ? $transfer->to_branch_id : $transfer->branch_id,
            ],
            lines: $lines,
        );
    }

    /** Whether the goods change branch, which makes it a send-and-receive (phase 4). */
    private function isInterbranch(ItemTransfer $transfer): bool
    {
        return $transfer->to_branch_id !== null
            && (string) $transfer->to_branch_id !== (string) $transfer->branch_id;
    }

    private function branchOfWarehouse(?string $warehouseId): ?string
    {
        return $warehouseId
            ? Warehouse::withoutGlobalScopes()->whereKey($warehouseId)->value('branch_id')
            : null;
    }

    /**
     * Cancel a transfer (revert stock changes if already completed)
     */
    public function cancelTransfer(ItemTransfer $transfer): ItemTransfer
    {
        return DB::transaction(function () use ($transfer) {
            if ($transfer->status === TransactionStatus::REVERSED) {
                throw ValidationException::withMessages([
                    'status' => [__('messages.transfer.already_cancelled')],
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
                // Every voucher the transfer posted: the freight, and for a
                // transfer between branches the send and the receipt (phase 4),
                // newest first.
                $transactions = Transaction::query()
                    ->where('reference_type', ItemTransfer::class)
                    ->where('reference_id', $transfer->id)
                    ->where('status', TransactionStatus::POSTED->value)
                    ->orderByDesc('id')
                    ->get();

                foreach ($transactions as $transaction) {
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
                ?? CompanyContext::glAccount('item-transfer-expense'),
            'currency_id' => $data['currency_id'] ?? CompanyContext::homeCurrency()?->id,
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

        $glAccounts = CompanyContext::glAccounts();
        $homeCurrency = CompanyContext::homeCurrency();

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
                    'items' => [__('messages.stock.insufficient_for_item', ['item' => $itemName, 'available' => $stockLevel['available'], 'required' => $quantity])],
                ]);
            }
        }
    }
}

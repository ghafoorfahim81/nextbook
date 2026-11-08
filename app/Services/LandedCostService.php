<?php

namespace App\Services;

use App\Support\CompanyContext;
use App\Models\Administration\Warehouse;

use App\Enums\LandedCostAllocationMethod;
use App\Enums\LandedCostStatus;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Account\Account;
use App\Models\Inventory\Item;
use App\Models\Inventory\LandedCost;
use App\Models\Inventory\LandedCostItem;
use App\Models\Inventory\StockMovement;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
use App\Models\Transaction\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class LandedCostService
{
    public function __construct(private readonly TransactionService $transactionService)
    {
    }

    public function syncItems(LandedCost $landedCost, array $payloadItems = []): Collection
    {
        $rows = $this->resolveRows($landedCost, $payloadItems);

        $landedCost->items()->forceDelete();

        $created = collect();

        foreach ($rows as $row) {
            $created->push($landedCost->items()->create([
                'purchase_item_id' => data_get($row, 'purchase_item_id'),
                'item_id' => data_get($row, 'item_id'),
                'quantity' => (float) data_get($row, 'quantity', 0),
                'unit_cost' => (float) data_get($row, 'unit_cost', 0),
                'weight' => (float) data_get($row, 'weight', 0),
                'volume' => (float) data_get($row, 'volume', 0),
                'warehouse_id' => data_get($row, 'warehouse_id'),
                'batch' => data_get($row, 'batch'),
                'expire_date' => data_get($row, 'expire_date'),
                'allocated_percentage' => (float) data_get($row, 'allocated_percentage', 0),
                'allocated_amount' => (float) data_get($row, 'allocated_amount', 0),
                'item_cost_before' => (float) data_get($row, 'item_cost_before', 0),
                'item_cost_after' => (float) data_get($row, 'item_cost_after', 0),
            ]));
        }

        return $created;
    }

    public function syncPurchases(LandedCost $landedCost, array $purchaseIds = []): void
    {
        $purchaseIds = collect($purchaseIds)
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $landedCost->purchases()->detach();

        if (! empty($purchaseIds)) {
            // The pivot carries the company so both of its composite foreign
            // keys can share one column, which is what makes a landed cost of
            // one company unattachable to another company's purchase. An
            // `attach` writes raw, so no model hook fills it in.
            $companyId = $landedCost->company_id;

            $landedCost->purchases()->attach(collect($purchaseIds)->mapWithKeys(
                function (string $purchaseId) use ($companyId): array {
                    return [
                        $purchaseId => [
                            'id' => (string) Str::ulid(),
                            'company_id' => $companyId,
                        ],
                    ];
                }
            )->all());
        }

        $landedCost->update([
            'purchase_id' => $purchaseIds[0] ?? null,
        ]);
    }

    /**
     * Replace the category breakdown for this landed cost.
     *
     * Force-deleted and rebuilt on every save, same as syncItems() — the
     * breakdown has no identity of its own outside "what this landed cost is
     * currently split into".
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function syncCategoryAllocations(LandedCost $landedCost, array $rows = []): void
    {
        $landedCost->categoryAllocations()->forceDelete();

        foreach ($rows as $row) {
            $amount = round((float) data_get($row, 'amount', 0), 2);

            if ($amount <= 0) {
                continue;
            }

            $landedCost->categoryAllocations()->create([
                'landed_cost_category_id' => data_get($row, 'landed_cost_category_id'),
                'amount' => $amount,
            ]);
        }
    }

    /**
     * (Re)create the draft transaction that funds this landed cost.
     *
     * Called from both store() and update(): a landed cost carries its
     * transaction from the moment it is created, in 'draft' status, so that
     * post() never has to build GL lines — it only flips two statuses. Editing
     * a still-draft landed cost force-deletes the old transaction and its
     * lines and posts a fresh one, the same replace-on-edit pattern
     * PaymentController::update() uses for the same reason: a draft entry has
     * no downstream reporting depending on it yet, so there is nothing to
     * reverse, only something to redo.
     *
     * @param array{bank_account_id: string, currency_id: string, rate: float, date: string} $header
     */
    public function syncDraftTransaction(LandedCost $landedCost, array $header): Transaction
    {
        $existing = $landedCost->transaction()->first();

        if ($existing) {
            $existing->lines()->forceDelete();
            $existing->forceDelete();
        }

        $amount = round((float) $landedCost->total_cost, 2);

        $inventoryLine = [
            'account_id' => $this->resolveInventoryStockAccountId(),
            'debit' => $amount,
            'credit' => 0,
            'remark' => 'Inventory capitalization for landed cost',
        ];

        $bankLine = [
            'account_id' => $header['bank_account_id'],
            'debit' => 0,
            'credit' => $amount,
            'remark' => 'Landed cost funded from account',
        ];

        return $this->transactionService->post(
            header: [
                'currency_id' => $header['currency_id'],
                'rate' => $header['rate'],
                'date' => $header['date'],
                'remark' => 'Landed cost #' . $landedCost->id,
                // Every reader — LandedCost::transaction() (morphOne), reports,
                // the trash screen — resolves the type via getMorphClass(),
                // which returns the registered morph-map alias ('landed_cost')
                // rather than the FQCN once a mapping exists. Writing the raw
                // class name here would silently orphan the relation.
                'reference_type' => $landedCost->getMorphClass(),
                'reference_id' => $landedCost->id,
                'status' => TransactionStatus::DRAFT->value,
            ],
            lines: [$inventoryLine, $bankLine],
        );
    }

    public function preview(LandedCost $landedCost, array $payload = []): array
    {
        $rows = $this->resolveRows($landedCost, data_get($payload, 'items', []), data_get($payload, 'purchase_ids', []));
        $totalCost = (float) data_get($payload, 'total_cost', $landedCost->total_cost);
        $method = $this->resolveMethod(data_get($payload, 'allocation_method', $landedCost->allocation_method?->value ?? $landedCost->allocation_method));

        $rate = (float) (data_get($payload, 'rate') ?: $landedCost->transaction?->rate ?: 1);

        return $this->calculatePreview($rows, $totalCost, $method, $rate > 0 ? $rate : 1.0);
    }

    /**
     * The landed cost's own exchange rate: home-currency units per one unit of
     * the currency the extra cost was paid in. Amounts on a landed cost (total,
     * allocations) are in that currency, while stock costs are kept in the home
     * currency, so every allocation is multiplied by this before it touches stock.
     */
    private function homeRate(LandedCost $landedCost): float
    {
        $rate = (float) ($landedCost->transaction?->rate ?: 1);

        return $rate > 0 ? $rate : 1.0;
    }

    public function allocate(LandedCost $landedCost, array $payload = []): LandedCost
    {
        return DB::transaction(function () use ($landedCost, $payload) {
            $landedCost->loadMissing(['purchases.items.item', 'items.item']);

            $preview = $this->preview($landedCost, $payload);

            $this->syncPurchases($landedCost, data_get($payload, 'purchase_ids', data_get($payload, 'purchase_id', [])));

            $landedCost->update([
                'date' => data_get($payload, 'date', $landedCost->date),
                'total_cost' => data_get($payload, 'total_cost', $landedCost->total_cost),
                'allocated_total' => $preview['allocated_total'],
                'bank_account_id' => data_get($payload, 'bank_account_id', $landedCost->bank_account_id),
                'allocation_method' => data_get($payload, 'allocation_method', $landedCost->allocation_method?->value ?? $landedCost->allocation_method),
                'status' => LandedCostStatus::Allocated->value,
                'notes' => data_get($payload, 'notes', $landedCost->notes),
            ]);

            $this->syncItems($landedCost, $preview['rows']);

            // The voucher post() will book carries the amount, account, currency
            // and rate it was built with. Changing the total here without
            // rebuilding it left a posted GL entry at the old figure.
            $this->syncDraftTransaction($landedCost, [
                'bank_account_id' => $landedCost->bank_account_id,
                'currency_id' => data_get($payload, 'currency_id', $landedCost->transaction?->currency_id),
                'rate' => (float) data_get($payload, 'rate', $landedCost->transaction?->rate ?: 1),
                'date' => $landedCost->date?->toDateString() ?? (string) data_get($payload, 'date'),
            ]);

            return $landedCost->fresh(['purchases.items.item', 'items.item']);
        });
    }

    /**
     * Posting a landed cost that already carries its draft transaction (see
     * syncDraftTransaction(), called from the controller at create/update
     * time) is now just two status flips. Everything that used to happen here
     * — building GL lines, posting a Transaction — happened when the record
     * was saved. What's left is (a) a final re-check that the allocation
     * still matches total_cost, which also finalises landed_cost_items, and
     * (b) the FIFO cost push into StockMovement/Item.avg_cost, which must
     * stay gated behind posting: a draft is still editable and discardable,
     * so item unit costs may not move until the landed cost is final.
     */
    public function post(LandedCost $landedCost): array
    {
        return DB::transaction(function () use ($landedCost) {
            // Lock and re-read the row: two posts arriving together both saw
            // 'draft' and each added the cost to the stock layers.
            $landedCost = $this->lockFresh($landedCost);
            $landedCost->loadMissing(['purchases.items.item', 'items.item', 'transaction']);

            $currentStatus = $landedCost->status instanceof LandedCostStatus ? $landedCost->status->value : (string) $landedCost->status;

            if ($currentStatus === LandedCostStatus::Posted->value) {
                throw ValidationException::withMessages([
                    'landed_cost' => __('general.landed_cost_already_posted'),
                ]);
            }

            if ($currentStatus === LandedCostStatus::Reversed->value) {
                throw ValidationException::withMessages([
                    'landed_cost' => __('general.landed_cost_reversed_cannot_be_posted'),
                ]);
            }

            $transaction = $landedCost->transaction;

            if (! $transaction) {
                throw ValidationException::withMessages([
                    'landed_cost' => __('general.landed_cost_related_transaction_not_found'),
                ]);
            }

            if ($landedCost->items->isEmpty()) {
                $this->syncItems($landedCost);
                $landedCost->load('items.item');
            }

            $items = $landedCost->items;
            $hasStoredAllocations = (float) $items->sum('allocated_amount') > 0;
            $method = $hasStoredAllocations
                ? LandedCostAllocationMethod::Manual
                : $this->resolveMethod($landedCost->allocation_method?->value ?? $landedCost->allocation_method);

            $preview = $this->calculatePreview(
                $items->map(fn (LandedCostItem $item) => [
                    'purchase_item_id' => $item->purchase_item_id,
                    'item_id' => $item->item_id,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'weight' => $item->weight,
                    'volume' => $item->volume,
                    'warehouse_id' => $item->warehouse_id,
                    'batch' => $item->batch,
                    'expire_date' => $item->expire_date?->toDateString(),
                    'allocated_amount' => (float) $item->allocated_amount,
                    'allocated_percentage' => (float) $item->allocated_percentage,
                ]),
                (float) $landedCost->total_cost,
                $method,
                $this->homeRate($landedCost)
            );

            $this->assertAllocationMatchesTotalCost((float) $landedCost->total_cost, (float) $preview['allocated_total']);

            $landedCost->items()->forceDelete();
            $this->syncItems($landedCost, $preview['rows']);

            $this->applyInventoryAdjustments($landedCost, collect($preview['rows']), $this->homeRate($landedCost));

            $landedCost->update([
                'allocated_total' => $preview['allocated_total'],
                'status' => LandedCostStatus::Posted->value,
            ]);

            // postDraft(), not a status flip: a draft keeps its lines only in
            // posting_payload, so flipping the status alone left a "posted"
            // voucher with no lines — the cost reached the item layers but the
            // GL never debited inventory or credited the cash account.
            $this->transactionService->postDraft($transaction);

            return [
                'landed_cost' => $landedCost->fresh(['purchases.items.item', 'items.item', 'transaction']),
                'transaction' => $transaction->fresh(),
            ];
        });
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{rows: array<int, array<string, mixed>>, allocated_total: float}
     */
    private function calculatePreview(array|Collection $rows, float $totalCost, LandedCostAllocationMethod $method, float $rate = 1.0): array
    {
        $rows = collect($rows)
            ->filter(fn ($row) => filled(data_get($row, 'item_id')))
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => __('general.landed_cost_no_items_to_allocate'),
            ]);
        }

        $rows = $this->withShippingBasis($rows, $method);

        $prepared = $rows->map(function (array|LandedCostItem $row) use ($method) {
            $quantity = (float) data_get($row, 'quantity', 0);
            $unitCost = (float) data_get($row, 'unit_cost', 0);
            $weight = (float) data_get($row, 'weight', 0);
            $volume = (float) data_get($row, 'volume', 0);

            $basisValue = match ($method) {
                LandedCostAllocationMethod::ByQuantity => $quantity,
                // withShippingBasis() has already refused any row without a weight/volume.
                LandedCostAllocationMethod::ByWeight => $weight,
                LandedCostAllocationMethod::ByVolume => $volume,
                LandedCostAllocationMethod::Equal => 1,
                LandedCostAllocationMethod::Manual => 0,
                default => $quantity * $unitCost,
            };

            return [
                'purchase_item_id' => data_get($row, 'purchase_item_id'),
                'item_id' => data_get($row, 'item_id'),
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'weight' => $weight,
                'volume' => $volume,
                'warehouse_id' => data_get($row, 'warehouse_id'),
                'batch' => data_get($row, 'batch'),
                'expire_date' => data_get($row, 'expire_date') ? data_get($row, 'expire_date') : null,
                'item_cost_before' => round($quantity * $unitCost, 2),
                'allocated_amount' => (float) data_get($row, 'allocated_amount', 0),
                'basis_value' => $basisValue,
            ];
        });

        if ($method === LandedCostAllocationMethod::Manual) {
            $allocatedTotal = 0.0;

            $rowsOut = $prepared->values()->map(function (array $row) use ($totalCost, $rate, &$allocatedTotal) {
                $allocation = round((float) $row['allocated_amount'], 2);
                $allocatedTotal = round($allocatedTotal + $allocation, 2);

                $row['allocated_percentage'] = $totalCost > 0
                    ? round(($allocation / $totalCost) * 100, 4)
                    : 0;
                $row['allocated_amount'] = $allocation;
                $row['item_cost_after'] = round($row['item_cost_before'] + ($allocation * $rate), 2);

                unset($row['basis_value']);

                return $row;
            })->all();

            return [
                'rows' => $rowsOut,
                'allocated_total' => $allocatedTotal,
            ];
        }

        $basisTotal = (float) $prepared->sum('basis_value');

        if ($basisTotal <= 0) {
            throw ValidationException::withMessages([
                'items' => __('general.landed_cost_allocation_basis_must_be_greater_than_zero'),
            ]);
        }

        $remaining = round($totalCost, 2);
        $allocatedTotal = 0.0;
        $lastIndex = $prepared->count() - 1;

        $rowsOut = $prepared->values()->map(function (array $row, int $index) use (&$remaining, $basisTotal, $totalCost, $lastIndex, $rate, &$allocatedTotal) {
            $allocation = $index === $lastIndex
                ? round($remaining, 2)
                : round(($totalCost * $row['basis_value']) / $basisTotal, 2);

            $remaining = round($remaining - $allocation, 2);
            $allocatedTotal = round($allocatedTotal + $allocation, 2);

            $row['allocated_percentage'] = round(($row['basis_value'] / $basisTotal) * 100, 4);
            $row['allocated_amount'] = $allocation;
            $row['item_cost_after'] = round($row['item_cost_before'] + ($allocation * $rate), 2);

            unset($row['basis_value']);

            return $row;
        })->all();

        return [
            'rows' => $rowsOut,
            'allocated_total' => $allocatedTotal,
        ];
    }

    /**
     * For a by-weight or by-volume split, set each row's total weight/volume
     * (quantity x the item's own figure) from the item record.
     *
     * Never falls back to quantity: that split looks like a weight split but
     * is not, so an item with no weight/volume stops the allocation with a
     * message naming it. Mixed units (kg next to g, cm next to m) stop it too,
     * because the figures could not be added up.
     */
    private function withShippingBasis(Collection $rows, LandedCostAllocationMethod $method): Collection
    {
        if (! in_array($method, [LandedCostAllocationMethod::ByWeight, LandedCostAllocationMethod::ByVolume], true)) {
            return $rows;
        }

        $byWeight = $method === LandedCostAllocationMethod::ByWeight;
        $items = Item::query()->whereIn('id', $rows->pluck('item_id')->filter()->unique()->all())->get()->keyBy('id');

        $missing = [];
        $units = [];

        $rows = $rows->map(function ($row) use ($items, $byWeight, &$missing, &$units) {
            $item = $items->get((string) data_get($row, 'item_id'));
            $quantity = (float) data_get($row, 'quantity', 0);

            $figure = $byWeight
                ? (float) $item?->weight
                : (float) $item?->length * (float) $item?->width * (float) $item?->height;

            if ($item === null || $figure <= 0) {
                $missing[$item?->id ?? (string) data_get($row, 'item_id')] = $item?->name ?? (string) data_get($row, 'item_id');

                return $row;
            }

            $units[(string) ($byWeight ? $item->weight_unit_id : $item->dimension_unit_id)] = true;

            $row = $row instanceof LandedCostItem ? $row->toArray() : (array) $row;
            $row[$byWeight ? 'weight' : 'volume'] = $figure * $quantity;

            return $row;
        });

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'allocation_method' => __(
                    $byWeight ? 'general.landed_cost_items_missing_weight' : 'general.landed_cost_items_missing_volume',
                    ['items' => implode('، ', array_values($missing))]
                ),
            ]);
        }

        if (count($units) > 1) {
            throw ValidationException::withMessages([
                'allocation_method' => __($byWeight ? 'general.landed_cost_weight_units_differ' : 'general.landed_cost_dimension_units_differ'),
            ]);
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>>|Collection<int, mixed> $rows
     */
    private function resolveRows(LandedCost $landedCost, array|Collection $rows, array|Collection $purchaseIds = []): array
    {
        $normalizedRows = collect($rows)
            ->filter(fn ($row) => filled(data_get($row, 'item_id')))
            ->values();

        if ($normalizedRows->isNotEmpty()) {
            $normalizedRows = $this->withPurchaseFigures($normalizedRows);

            return $normalizedRows->map(function (array $row) {
                return [
                    'purchase_item_id' => data_get($row, 'purchase_item_id'),
                    'item_id' => data_get($row, 'item_id'),
                    'quantity' => (float) data_get($row, 'quantity', 0),
                    'unit_cost' => (float) data_get($row, 'unit_cost', 0),
                    'weight' => (float) data_get($row, 'weight', 0),
                    'volume' => (float) data_get($row, 'volume', 0),
                    'warehouse_id' => data_get($row, 'warehouse_id'),
                    'batch' => data_get($row, 'batch'),
                    'expire_date' => data_get($row, 'expire_date'),
                    'allocated_amount' => (float) data_get($row, 'allocated_amount', 0),
                    'allocated_percentage' => (float) data_get($row, 'allocated_percentage', 0),
                    // Carried through rather than recomputed: syncItems() feeds
                    // calculatePreview() output straight back into here, and
                    // dropping these two would persist a zero landed unit cost
                    // for every line. Fall back to quantity x unit_cost so rows
                    // that never went through a preview still land sane values.
                    'item_cost_before' => (float) data_get($row, 'item_cost_before', (float) data_get($row, 'quantity', 0) * (float) data_get($row, 'unit_cost', 0)),
                    'item_cost_after' => (float) data_get($row, 'item_cost_after', ((float) data_get($row, 'quantity', 0) * (float) data_get($row, 'unit_cost', 0)) + (float) data_get($row, 'allocated_amount', 0)),
                ];
            })->all();
        }

        $purchaseIds = collect($purchaseIds)
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($purchaseIds->isEmpty()) {
            if ($landedCost->relationLoaded('purchases') && $landedCost->purchases->isNotEmpty()) {
                $purchaseIds = $landedCost->purchases->pluck('id')->values();
            } elseif ($landedCost->purchase_id) {
                $purchaseIds = collect([$landedCost->purchase_id]);
            }
        }

        if ($purchaseIds->isEmpty()) {
            return [];
        }

        $purchases = Purchase::query()
            ->with(['items.item', 'items.purchase', 'transaction'])
            ->whereIn('id', $purchaseIds->all())
            ->get();

        if ($purchases->isEmpty()) {
            return [];
        }

        return $purchases->flatMap(function (Purchase $purchase) {
            return $purchase->items->map(function (PurchaseItem $item) use ($purchase) {
                return [
                    'purchase_id' => $purchase->id,
                    'purchase_number' => $purchase->number,
                    'purchase_item_id' => $item->id,
                    'item_id' => $item->item_id,
                    'quantity' => (float) $item->quantity,
                    // Home currency, like the stock cost the allocation lands on.
                    'unit_cost' => (float) $item->unit_price * max((float) ($purchase->transaction?->rate ?: 1), 0.0000001),
                    'weight' => 0,
                    'volume' => 0,
                    'warehouse_id' => $item->warehouse_id,
                    'batch' => $item->batch,
                    'expire_date' => $item->expire_date?->toDateString(),
                ];
            });
        })->values()->all();
    }

    /**
     * Quantity, cost, warehouse, batch and expiry of a row that points at a
     * purchase line come from that line, never from the request: the client
     * only chooses which lines take part and, for a manual split, how much each
     * gets. Otherwise a hand-made request could skew the split or point the
     * cost at stock the picked bills never received.
     */
    private function withPurchaseFigures(Collection $rows): Collection
    {
        $lines = PurchaseItem::query()
            ->with(['purchase.transaction'])
            ->whereIn('id', $rows->pluck('purchase_item_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($lines) {
            $line = $lines->get((string) data_get($row, 'purchase_item_id'));

            if ($line === null) {
                return $row;
            }

            $rate = max((float) ($line->purchase?->transaction?->rate ?: 1), 0.0000001);

            return array_merge((array) $row, [
                'purchase_id' => $line->purchase_id,
                'item_id' => $line->item_id,
                'quantity' => (float) $line->quantity,
                'unit_cost' => (float) $line->unit_price * $rate,
                'warehouse_id' => $line->warehouse_id,
                'batch' => $line->batch,
                'expire_date' => $line->expire_date?->toDateString(),
            ]);
        });
    }

    private function resolveMethod(string|LandedCostAllocationMethod|null $method): LandedCostAllocationMethod
    {
        if ($method instanceof LandedCostAllocationMethod) {
            return $method;
        }

        return LandedCostAllocationMethod::tryFrom((string) $method) ?? LandedCostAllocationMethod::ByValue;
    }

    /** The row, re-read under a write lock so a concurrent post/reverse waits and then sees the new status. */
    private function lockFresh(LandedCost $landedCost): LandedCost
    {
        return LandedCost::query()
            ->whereKey($landedCost->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function resolveInventoryStockAccountId(): string
    {
        return data_get(CompanyContext::glAccounts(), 'inventory-stock')
            ?? Account::withoutGlobalScopes()->where('slug', 'inventory-stock')->value('id')
            ?? throw ValidationException::withMessages([
                'items' => __('general.landed_cost_inventory_stock_account_could_not_be_resolved'),
            ]);
    }


    /**
     * Undo a posted landed cost: reverse its GL entry and take the allocated
     * cost back out of the purchase stock layers it was added to.
     */
    public function reverse(LandedCost $landedCost, ?string $reason = null): LandedCost
    {
        return DB::transaction(function () use ($landedCost, $reason) {
            $landedCost = $this->lockFresh($landedCost);
            $landedCost->loadMissing(['items', 'transaction']);

            $status = $landedCost->status instanceof LandedCostStatus ? $landedCost->status->value : (string) $landedCost->status;

            if ($status !== LandedCostStatus::Posted->value) {
                throw ValidationException::withMessages([
                    'status' => [__('messages.document.only_posted_can_be_reversed')],
                ]);
            }

            $transaction = $landedCost->transaction;

            if (! $transaction) {
                throw ValidationException::withMessages([
                    'landed_cost' => __('general.landed_cost_related_transaction_not_found'),
                ]);
            }

            $rows = $landedCost->items->map(fn (LandedCostItem $item) => [
                'purchase_item_id' => $item->purchase_item_id,
                'item_id' => $item->item_id,
                'warehouse_id' => $item->warehouse_id,
                'batch' => $item->batch,
                'expire_date' => $item->expire_date?->toDateString(),
                'allocated_amount' => (float) $item->allocated_amount,
            ]);

            $this->applyInventoryAdjustments($landedCost, $rows, $this->homeRate($landedCost), -1);

            $this->transactionService->reverse($transaction, $reason, (string) $landedCost->number, $landedCost->getMorphClass());

            $landedCost->update(['status' => LandedCostStatus::Reversed->value]);

            return $landedCost->fresh(['purchases.items.item', 'items.item', 'transaction']);
        });
    }

    /**
     * @param  int  $direction  1 adds the allocation to the stock layers, -1 takes it back out.
     */
    private function applyInventoryAdjustments(LandedCost $landedCost, Collection $rows, float $rate = 1.0, int $direction = 1): void
    {
        $itemIds = [];

        $rows->each(function (array $row) use ($landedCost, $rate, $direction, &$itemIds) {
            $allocation = round((float) data_get($row, 'allocated_amount', 0) * $rate, 4) * $direction;

            if ($allocation == 0.0) {
                return;
            }

            $itemId = (string) data_get($row, 'item_id');
            $purchaseItem = filled(data_get($row, 'purchase_item_id'))
                ? PurchaseItem::query()->find(data_get($row, 'purchase_item_id'))
                : null;

            $purchaseId = $purchaseItem?->purchase_id
                ?? data_get($row, 'purchase_id')
                ?? $landedCost->purchase_id;
            $warehouseId = $purchaseItem?->warehouse_id ?? data_get($row, 'warehouse_id');
            $batch = $purchaseItem?->batch ?? data_get($row, 'batch');
            $expireDate = $purchaseItem?->expire_date?->toDateString() ?: data_get($row, 'expire_date');

            $movements = $this->relatedPurchaseMovements(
                $landedCost,
                $itemId,
                $purchaseId,
                $warehouseId,
                $batch,
                $expireDate,
            );

            if ($movements->isEmpty()) {
                $movements = $this->relatedPurchaseMovements(
                    $landedCost,
                    $itemId,
                    $purchaseId,
                    $warehouseId,
                    null,
                    null,
                );
            }

            if ($movements->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => __('general.landed_cost_related_stock_movement_not_found'),
                ]);
            }

            $qtyTotal = (float) $movements->sum('quantity');

            if ($qtyTotal <= 0) {
                return;
            }

            $remaining = $allocation;
            $lastIndex = $movements->count() - 1;

            foreach ($movements->values() as $index => $movement) {
                $qty = (float) $movement->quantity;
                $share = $index === $lastIndex
                    ? round($remaining, 4)
                    : round($allocation * ($qty / $qtyTotal), 4);
                $remaining = round($remaining - $share, 4);

                if ($qty <= 0) {
                    continue;
                }

                $movement->unit_cost = round(((float) $movement->unit_cost) + ($share / $qty), 4);
                $movement->save();
            }

            $itemIds[$itemId] = $itemId;
        });

        foreach ($itemIds as $itemId) {
            $this->recalculateAvgCostForItem($itemId);
        }
    }

    private function relatedPurchaseMovements(
        LandedCost $landedCost,
        string $itemId,
        ?string $purchaseId,
        mixed $warehouseId,
        mixed $batch,
        mixed $expireDate,
    ): Collection {
        return StockMovement::query()
            ->whereIn('warehouse_id', Warehouse::idsOfBranch($landedCost->branch_id))
            // A voided or cancelled receipt no longer holds stock; capitalising
            // onto it would put cost on goods the business does not own.
            ->whereNotIn('status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            ->where('item_id', $itemId)
            ->where('movement_type', StockMovementType::IN)
            ->where('source', StockSourceType::PURCHASE)
            ->where('reference_type', Purchase::class)
            ->when($purchaseId, fn ($query) => $query->where('reference_id', $purchaseId))
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($batch, fn ($query) => $query->where('batch', $batch))
            ->when($expireDate, fn ($query) => $query->whereDate('expire_date', $expireDate))
            ->lockForUpdate()
            ->get();
    }

    /**
     * Re-weigh the item's average with the stock module's own replay, which
     * converts units, skips voided movements and nets purchase returns — a
     * private copy here drifted from it on all three.
     */
    private function recalculateAvgCostForItem(string $itemId): void
    {
        $stock = app(StockService::class);

        $stock->recalculateItemAverage($itemId);

        // Each variant carries its own average too, replayed the same way.
        $stock->recalculateVariantAverageCosts($itemId);
    }

    private function assertAllocationMatchesTotalCost(float $totalCost, float $allocatedTotal): void
    {
        if (abs(round($totalCost, 2) - round($allocatedTotal, 2)) > 0.01) {
            throw ValidationException::withMessages([
                'allocated_total' => __('general.landed_cost_allocation_must_match_total_cost'),
            ]);
        }
    }
}

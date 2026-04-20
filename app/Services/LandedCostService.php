<?php

namespace App\Services;

use App\Enums\LandedCostAllocationMethod;
use App\Enums\LandedCostStatus;
use App\Models\Account\Account;
use App\Models\Administration\Currency;
use App\Models\Inventory\LandedCost;
use App\Models\Inventory\LandedCostItem;
use App\Models\Inventory\StockBalance;
use App\Models\JournalEntry\JournalEntry as JournalEntryRecord;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
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
            $landedCost->purchases()->attach(collect($purchaseIds)->mapWithKeys(function (string $purchaseId): array {
                return [
                    $purchaseId => [
                        'id' => (string) Str::ulid(),
                    ],
                ];
            })->all());
        }

        $landedCost->update([
            'purchase_id' => $purchaseIds[0] ?? null,
        ]);
    }

    public function preview(LandedCost $landedCost, array $payload = []): array
    {
        $rows = $this->resolveRows($landedCost, data_get($payload, 'items', []), data_get($payload, 'purchase_ids', []));
        $totalCost = (float) data_get($payload, 'total_cost', $landedCost->total_cost);
        $method = $this->resolveMethod(data_get($payload, 'allocation_method', $landedCost->allocation_method?->value ?? $landedCost->allocation_method));

        return $this->calculatePreview($rows, $totalCost, $method);
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
                'allocation_method' => data_get($payload, 'allocation_method', $landedCost->allocation_method?->value ?? $landedCost->allocation_method),
                'status' => LandedCostStatus::Allocated->value,
                'notes' => data_get($payload, 'notes', $landedCost->notes),
            ]);

            $this->syncItems($landedCost, $preview['rows']);

            return $landedCost->fresh(['purchases.items.item', 'items.item']);
        });
    }

    public function post(LandedCost $landedCost): array
    {
        return DB::transaction(function () use ($landedCost) {
            $landedCost->loadMissing(['purchases.items.item', 'items.item']);

            if (($landedCost->status instanceof LandedCostStatus ? $landedCost->status->value : (string) $landedCost->status) === LandedCostStatus::Posted->value) {
                throw ValidationException::withMessages([
                    'landed_cost' => __('general.landed_cost_already_posted'),
                ]);
            }

            if ($landedCost->items->isEmpty()) {
                $this->syncItems($landedCost);
                $landedCost->load('items.item');
            }

            $items = $landedCost->items;

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
                ]),
                (float) $landedCost->total_cost,
                $this->resolveMethod($landedCost->allocation_method?->value ?? $landedCost->allocation_method)
            );

            $landedCost->items()->forceDelete();
            $this->syncItems($landedCost, $preview['rows']);

            $landedCost->update([
                'allocated_total' => $preview['allocated_total'],
                'status' => LandedCostStatus::Allocated->value,
            ]);

            $journalEntry = JournalEntryRecord::create([
                'number' => (int) (JournalEntryRecord::max('number') ? JournalEntryRecord::max('number') + 1 : 1),
                'date' => $landedCost->date,
                'remark' => 'Landed cost #' . $landedCost->id,
                'status' => 'posted',
                'branch_id' => $landedCost->branch_id,
            ]);

            $inventoryLine = [
                'account_id' => $this->resolveInventoryStockAccountId(),
                'ledger_id' => null,
                'debit' => round($preview['allocated_total'], 2),
                'credit' => 0,
                'remark' => 'Inventory capitalization for landed cost',
            ];

            $clearingLine = [
                'account_id' => $this->resolveClearingAccountId(),
                'ledger_id' => null,
                'debit' => 0,
                'credit' => round($preview['allocated_total'], 2),
                'remark' => 'Landed costs clearing for landed cost',
            ];

            $transaction = $this->transactionService->post(
                header: [
                    'currency_id' => $this->resolveHomeCurrencyId(),
                    'rate' => 1,
                    'date' => $landedCost->date?->toDateString() ?? now()->toDateString(),
                    'remark' => 'Landed cost #' . $landedCost->id,
                    'reference_type' => JournalEntryRecord::class,
                    'reference_id' => $journalEntry->id,
                    'status' => 'posted',
                ],
                lines: [
                    $inventoryLine,
                    $clearingLine,
                ],
            );

            $this->applyInventoryAdjustments($landedCost, collect($preview['rows']));

            $landedCost->update([
                'allocated_total' => $preview['allocated_total'],
                'status' => LandedCostStatus::Posted->value,
            ]);

            return [
                'landed_cost' => $landedCost->fresh(['purchases.items.item', 'items.item']),
                'journal_entry' => $journalEntry,
                'transaction' => $transaction,
            ];
        });
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{rows: array<int, array<string, mixed>>, allocated_total: float}
     */
    private function calculatePreview(array|Collection $rows, float $totalCost, LandedCostAllocationMethod $method): array
    {
        $rows = collect($rows)
            ->filter(fn ($row) => filled(data_get($row, 'item_id')))
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => __('general.landed_cost_no_items_to_allocate'),
            ]);
        }

        $prepared = $rows->map(function (array|LandedCostItem $row) use ($method) {
            $quantity = (float) data_get($row, 'quantity', 0);
            $unitCost = (float) data_get($row, 'unit_cost', 0);
            $weight = (float) data_get($row, 'weight', 0);
            $volume = (float) data_get($row, 'volume', 0);

            $basisValue = match ($method) {
                LandedCostAllocationMethod::ByQuantity => $quantity,
                LandedCostAllocationMethod::ByWeight => $weight > 0 ? $weight : $quantity,
                LandedCostAllocationMethod::ByVolume => $volume > 0 ? $volume : $quantity,
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
                'basis_value' => $basisValue,
            ];
        });

        $basisTotal = (float) $prepared->sum('basis_value');

            if ($basisTotal <= 0) {
            throw ValidationException::withMessages([
                'items' => __('general.landed_cost_allocation_basis_must_be_greater_than_zero'),
            ]);
        }

        $remaining = round($totalCost, 2);
        $allocatedTotal = 0.0;
        $lastIndex = $prepared->count() - 1;

        $rowsOut = $prepared->values()->map(function (array $row, int $index) use (&$remaining, $basisTotal, $totalCost, $lastIndex, &$allocatedTotal) {
            $allocation = $index === $lastIndex
                ? round($remaining, 2)
                : round(($totalCost * $row['basis_value']) / $basisTotal, 2);

            $remaining = round($remaining - $allocation, 2);
            $allocatedTotal = round($allocatedTotal + $allocation, 2);

            $row['allocated_percentage'] = round(($row['basis_value'] / $basisTotal) * 100, 4);
            $row['allocated_amount'] = $allocation;
            $row['item_cost_after'] = round($row['item_cost_before'] + $allocation, 2);

            unset($row['basis_value']);

            return $row;
        })->all();

        return [
            'rows' => $rowsOut,
            'allocated_total' => $allocatedTotal,
        ];
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
            ->with(['items.item', 'items.purchase'])
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
                    'unit_cost' => (float) $item->unit_price,
                    'weight' => 0,
                    'volume' => 0,
                    'warehouse_id' => $item->warehouse_id,
                    'batch' => $item->batch,
                    'expire_date' => $item->expire_date?->toDateString(),
                ];
            });
        })->values()->all();
    }

    private function resolveMethod(string|LandedCostAllocationMethod|null $method): LandedCostAllocationMethod
    {
        if ($method instanceof LandedCostAllocationMethod) {
            return $method;
        }

        return LandedCostAllocationMethod::tryFrom((string) $method) ?? LandedCostAllocationMethod::ByValue;
    }

    private function resolveInventoryStockAccountId(): string
    {
        return data_get(Cache::get('gl_accounts'), 'inventory-stock')
            ?? Account::withoutGlobalScopes()->where('slug', 'inventory-stock')->value('id')
            ?? throw ValidationException::withMessages([
                'items' => __('general.landed_cost_inventory_stock_account_could_not_be_resolved'),
            ]);
    }

    private function resolveClearingAccountId(): string
    {
        return data_get(Cache::get('gl_accounts'), 'landed-costs-clearing')
            ?? Account::withoutGlobalScopes()->where('slug', 'landed-costs-clearing')->value('id')
            ?? data_get(Cache::get('gl_accounts'), 'freight-customs-clearing')
            ?? Account::withoutGlobalScopes()->where('slug', 'freight-customs-clearing')->value('id')
            ?? throw ValidationException::withMessages([
                'items' => __('general.landed_cost_landed_costs_clearing_account_could_not_be_resolved'),
            ]);
    }

    private function resolveHomeCurrencyId(): string
    {
        return data_get(Cache::get('home_currency'), 'id')
            ?? Currency::query()->where('is_base_currency', true)->value('id')
            ?? throw ValidationException::withMessages([
                'currency' => __('general.landed_cost_home_currency_could_not_be_resolved'),
            ]);
    }

    private function applyInventoryAdjustments(LandedCost $landedCost, Collection $rows): void
    {
        $rows->each(function (array $row) use ($landedCost) {
            $itemId = (string) data_get($row, 'item_id');
            $quantity = (float) data_get($row, 'quantity', 0);
            $allocation = (float) data_get($row, 'allocated_amount', 0);

            if ($quantity <= 0 || $allocation <= 0) {
                return;
            }

            $increment = round($allocation / $quantity, 4);

            $balances = StockBalance::query()
                ->where('branch_id', $landedCost->branch_id)
                ->where('item_id', $itemId)
                ->when(data_get($row, 'warehouse_id'), fn ($query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
                ->when(data_get($row, 'batch'), fn ($query, $batch) => $query->where('batch', $batch))
                ->when(data_get($row, 'expire_date'), fn ($query, $expireDate) => $query->whereDate('expire_date', $expireDate))
                ->get();

            if ($balances->isEmpty()) {
                return;
            }

            foreach ($balances as $balance) {
                $balance->average_cost = round(((float) $balance->average_cost) + $increment, 4);
                $balance->save();
            }
        });
    }
}

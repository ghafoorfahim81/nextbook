<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\AccountTransfer\AccountTransfer;
use App\Models\Administration\Branch;
use App\Models\Administration\Brand;
use App\Models\Administration\Category;
use App\Models\Administration\Currency;
use App\Models\Administration\Department;
use App\Models\Administration\Designation;
use App\Models\Administration\Quantity;
use App\Models\Administration\Size;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseCategory;
use App\Models\Expense\ExpenseDetail;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockOut;
use App\Models\ItemTransfer\ItemTransfer;
use App\Models\JournalEntry\JournalClass;
use App\Models\JournalEntry\JournalEntry;
use App\Models\Ledger\Ledger;
use App\Models\Owner\Drawing;
use App\Models\Owner\Owner;
use App\Models\Payment\Payment;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
use App\Models\Receipt\Receipt;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class DeletedRecordService
{
    public const RETENTION_DAYS = 30;

    /**
     * Return the paginated deleted records payload for the index page.
     */
    public function indexPayload(array $filters = []): array
    {
        $moduleFilter = trim((string) ($filters['module'] ?? 'all'));
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;
        $page = max(1, (int) ($filters['page'] ?? 1));

        $records = $this->buildRecords($moduleFilter);

        if ($search !== '') {
            $needle = Str::lower($search);

            $records = $records->filter(static function (array $record) use ($needle): bool {
                return Str::contains($record['search_blob'], $needle);
            });
        }

        $records = $records
            ->sortByDesc('deleted_at_timestamp')
            ->values();

        $records = $this->attachHumanReadableReferences($records);

        $total = $records->count();
        $offset = ($page - 1) * $perPage;
        $items = $records->slice($offset, $perPage)->values();

        return [
            'records' => [
                'data' => $items->all(),
                'meta' => [
                    'current_page' => $page,
                    'last_page' => max(1, (int) ceil(max($total, 1) / $perPage)),
                    'per_page' => $perPage,
                    'from' => $total === 0 ? null : $offset + 1,
                    'to' => $total === 0 ? null : min($offset + $perPage, $total),
                    'total' => $total,
                ],
            ],
            'summary' => [
                'total' => $total,
                'modules' => max(0, $this->moduleOptions($records)->count() - 1),
                'expiring_soon' => $records->filter(fn (array $record) => $record['days_remaining'] <= 7)->count(),
            ],
            'moduleOptions' => $this->moduleOptions($records)->values()->all(),
        ];
    }

    /**
     * Restore a trashed record using the module registry.
     */
    public function restore(string $module, string $id): Model
    {
        $entry = $this->registry()[$module] ?? null;

        abort_unless($entry, 404, 'Deleted record module not found.');

        /** @var Model $record */
        $record = $entry['model']::withTrashed()->findOrFail($id);

        $this->runRestoreStrategy($record, $entry);

        return $record->fresh();
    }

    /**
     * Force delete a trashed record using the module registry.
     */
    public function forceDelete(string $module, string $id): void
    {
        $entry = $this->registry()[$module] ?? null;

        abort_unless($entry, 404, 'Deleted record module not found.');

        /** @var Model $record */
        $record = $entry['model']::withTrashed()->findOrFail($id);

        $this->runForceDeleteStrategy($record, $entry);
    }

    /**
     * Automatically force delete records older than the retention window.
     */
    public function cleanupExpired(): int
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);
        $deleted = 0;

        foreach ($this->registry() as $module => $entry) {
            $query = $this->applyModuleQuery($entry['model']::onlyTrashed(), $entry);

            foreach ($query->where('deleted_at', '<=', $cutoff)->get() as $record) {
                try {
                    $this->cleanupForceDelete($module, $record);
                    $deleted++;
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return $deleted;
    }

    private function cleanupForceDelete(string $module, Model $record): void
    {
        $entry = $this->registry()[$module] ?? null;

        if (! $entry) {
            return;
        }

        Model::withoutEvents(function () use ($record, $entry): void {
            $this->runForceDeleteStrategy($record, $entry);
        });
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function registry(): array
    {
        return [
            'branches' => [
                'label' => 'Branches',
                'model' => Branch::class,
                'title' => fn (Model $record) => $record->name,
            ],
            'categories' => [
                'label' => 'Categories',
                'model' => Category::class,
                'title' => fn (Model $record) => $record->name,
            ],
            'brands' => [
                'label' => 'Brands',
                'model' => Brand::class,
                'title' => fn (Model $record) => $record->name,
            ],
            'currencies' => [
                'label' => 'Currencies',
                'model' => Currency::class,
                'title' => fn (Model $record) => trim(($record->code ? $record->code.' ' : '').($record->name ?? '')),
            ],
            'departments' => [
                'label' => 'Departments',
                'model' => Department::class,
                'title' => fn (Model $record) => $record->name,
            ],
            'designations' => [
                'label' => 'Designations',
                'model' => Designation::class,
                'title' => fn (Model $record) => $record->name,
            ],
            'quantities' => [
                'label' => 'Quantities',
                'model' => Quantity::class,
                'title' => fn (Model $record) => $record->quantity,
            ],
            'sizes' => [
                'label' => 'Sizes',
                'model' => Size::class,
                'title' => fn (Model $record) => $record->name,
            ],
            'unit_measures' => [
                'label' => 'Unit Measures',
                'model' => UnitMeasure::class,
                'title' => fn (Model $record) => $record->name,
            ],
            'warehouses' => [
                'label' => 'Warehouses',
                'model' => Warehouse::class,
                'title' => fn (Model $record) => $record->name,
            ],
            'account_types' => [
                'label' => 'Account Types',
                'model' => AccountType::class,
                'title' => fn (Model $record) => $record->name,
            ],
            'accounts' => [
                'label' => 'Chart of Accounts',
                'model' => Account::class,
                'title' => fn (Model $record) => trim(($record->number ? $record->number.' - ' : '').($record->name ?? '')),
                'restore' => fn (Model $record) => $this->restoreLedgerOpeningRecord($record),
                'force_delete' => fn (Model $record) => $this->forceDeleteLedgerOpeningRecord($record),
            ],
            'ledgers' => [
                'label' => 'Ledgers',
                'model' => Ledger::class,
                'title' => fn (Model $record) => trim(($record->code ? $record->code.' - ' : '').($record->name ?? '')),
                'restore' => fn (Model $record) => $this->restoreLedgerOpeningRecord($record),
                'force_delete' => fn (Model $record) => $this->forceDeleteLedgerOpeningRecord($record),
            ],
            'customers' => [
                'label' => 'Customers',
                'model' => Ledger::class,
                'title' => fn (Model $record) => trim(($record->code ? $record->code.' - ' : '').($record->name ?? '')),
                'query' => fn (Builder $query) => $query->where('type', LedgerType::CUSTOMER->value),
                'restore' => fn (Model $record) => $this->restoreLedgerOpeningRecord($record),
                'force_delete' => fn (Model $record) => $this->forceDeleteLedgerOpeningRecord($record),
            ],
            'suppliers' => [
                'label' => 'Suppliers',
                'model' => Ledger::class,
                'title' => fn (Model $record) => trim(($record->code ? $record->code.' - ' : '').($record->name ?? '')),
                'query' => fn (Builder $query) => $query->where('type', LedgerType::SUPPLIER->value),
                'restore' => fn (Model $record) => $this->restoreLedgerOpeningRecord($record),
                'force_delete' => fn (Model $record) => $this->forceDeleteLedgerOpeningRecord($record),
            ],
            'items' => [
                'label' => 'Items',
                'model' => Item::class,
                'title' => fn (Model $record) => trim(($record->code ? $record->code.' - ' : '').($record->name ?? '')),
                'restore' => fn (Model $record) => $this->restoreItemRecord($record),
                'force_delete' => fn (Model $record) => $this->forceDeleteItemRecord($record),
            ],
            'purchases' => [
                'label' => 'Purchases',
                'model' => Purchase::class,
                'title' => fn (Model $record) => $record->number ?: $record->description ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreTransactionRecord($record, relations: ['items', 'stocks']),
                'force_delete' => fn (Model $record) => $this->forceDeleteTransactionRecord($record, relations: ['items', 'stocks']),
            ],
            'sales' => [
                'label' => 'Sales',
                'model' => Sale::class,
                'title' => fn (Model $record) => $record->number ?: $record->description ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreTransactionRecord($record, relations: ['items', 'stockOuts']),
                'force_delete' => fn (Model $record) => $this->forceDeleteTransactionRecord($record, relations: ['items', 'stockOuts']),
            ],
            'receipts' => [
                'label' => 'Receipts',
                'model' => Receipt::class,
                'title' => fn (Model $record) => $record->number ?: $record->narration ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreTransactionRecord($record),
                'force_delete' => fn (Model $record) => $this->forceDeleteTransactionRecord($record),
            ],
            'payments' => [
                'label' => 'Payments',
                'model' => Payment::class,
                'title' => fn (Model $record) => $record->number ?: $record->narration ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreTransactionRecord($record),
                'force_delete' => fn (Model $record) => $this->forceDeleteTransactionRecord($record),
            ],
            'account_transfers' => [
                'label' => 'Account Transfers',
                'model' => AccountTransfer::class,
                'title' => fn (Model $record) => $record->number ?: $record->remark ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreTransactionRecord($record),
                'force_delete' => fn (Model $record) => $this->forceDeleteTransactionRecord($record),
            ],
            'item_transfers' => [
                'label' => 'Item Transfers',
                'model' => ItemTransfer::class,
                'title' => fn (Model $record) => $record->remarks ?: $record->date?->format('Y-m-d') ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreSimpleRelations($record, ['items']),
                'force_delete' => fn (Model $record) => $this->forceDeleteSimpleRelations($record, ['items']),
            ],
            'expense_categories' => [
                'label' => 'Expense Categories',
                'model' => ExpenseCategory::class,
                'title' => fn (Model $record) => $record->name,
            ],
            'expenses' => [
                'label' => 'Expenses',
                'model' => Expense::class,
                'title' => fn (Model $record) => $record->remarks ?: $record->date?->format('Y-m-d') ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreTransactionRecord($record, relations: ['details']),
                'force_delete' => fn (Model $record) => $this->forceDeleteTransactionRecord($record, relations: ['details']),
            ],
            'owners' => [
                'label' => 'Owners',
                'model' => Owner::class,
                'title' => fn (Model $record) => $record->name,
                'restore' => fn (Model $record) => $this->restoreTransactionRecord($record),
                'force_delete' => fn (Model $record) => $this->forceDeleteTransactionRecord($record),
            ],
            'drawings' => [
                'label' => 'Drawings',
                'model' => Drawing::class,
                'title' => fn (Model $record) => $record->narration ?: $record->date?->format('Y-m-d') ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreTransactionRecord($record),
                'force_delete' => fn (Model $record) => $this->forceDeleteTransactionRecord($record),
            ],
            'users' => [
                'label' => 'Users',
                'model' => User::class,
                'title' => fn (Model $record) => $record->name,
            ],
            'journal_classes' => [
                'label' => 'Journal Classes',
                'model' => JournalClass::class,
                'title' => fn (Model $record) => trim(($record->code ? $record->code.' - ' : '').($record->name ?? '')),
            ],
            'journal_entries' => [
                'label' => 'Journal Entries',
                'model' => JournalEntry::class,
                'title' => fn (Model $record) => $record->number ?: $record->remark ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreTransactionRecord($record),
                'force_delete' => fn (Model $record) => $this->forceDeleteTransactionRecord($record),
            ],
        ];
    }

    /**
     * Build the trashed record collection for all registered modules.
     */
    private function buildRecords(string $moduleFilter): Collection
    {
        $records = collect();

        foreach ($this->registry() as $module => $entry) {
            if ($moduleFilter !== 'all' && $module !== $moduleFilter) {
                continue;
            }

            $query = $this->applyModuleQuery($entry['model']::onlyTrashed(), $entry);

            foreach ($query->get() as $record) {
                $records->push($this->normalizeRecord($module, $entry, $record));
            }
        }

        return $records;
    }

    private function applyModuleQuery(Builder $query, array $entry): Builder
    {
        if (isset($entry['query']) && is_callable($entry['query'])) {
            $query = ($entry['query'])($query) ?? $query;
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRecord(string $module, array $entry, Model $record): array
    {
        $attributes = $record->getAttributes();
        $title = (string) ($entry['title'] instanceof \Closure ? ($entry['title'])($record) : ($entry['title'] ?? ''));
        $title = trim($title) !== '' ? trim($title) : $this->fallbackTitle($record);

        $deletedAt = $record->deleted_at ? Carbon::parse($record->deleted_at) : null;
        $daysRemaining = $deletedAt
            ? max(0, self::RETENTION_DAYS - now()->startOfDay()->diffInDays($deletedAt->copy()->startOfDay()))
            : self::RETENTION_DAYS;
        $forceDeleteAt = $deletedAt?->copy()->addDays(self::RETENTION_DAYS);

        $fields = [];
        foreach ($attributes as $key => $value) {
            if (in_array($key, ['deleted_at', 'deleted_by', 'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'], true)) {
                continue;
            }

            $fields[] = [
                'key' => $key,
                'label' => Str::headline(str_replace('_', ' ', $key)),
                'value' => $value,
                'display_value' => null,
            ];
        }

        $metadata = [
            [
                'key' => 'deleted_by',
                'label' => 'Deleted By',
                'value' => $attributes['deleted_by'] ?? null,
                'display_value' => null,
            ],
            [
                'key' => 'deleted_at',
                'label' => 'Deleted At',
                'value' => $deletedAt?->toDateTimeString(),
                'display_value' => null,
            ],
            [
                'key' => 'auto_force_delete_at',
                'label' => 'Auto Force Delete At',
                'value' => $forceDeleteAt?->toDateTimeString(),
                'display_value' => null,
            ],
            [
                'key' => 'ip_address',
                'label' => 'IP Address',
                'value' => $attributes['ip_address'] ?? $attributes['deleted_ip'] ?? null,
                'display_value' => null,
            ],
        ];

        $dependencyWarning = null;
        if (method_exists($record, 'getDependencyMessage')) {
            try {
                $dependencyWarning = $record->getDependencyMessage();
            } catch (Throwable) {
                $dependencyWarning = null;
            }
        }

        return [
            'module' => $module,
            'module_label' => $entry['label'],
            'model_class' => $entry['model'],
            'record_id' => (string) $record->getKey(),
            'title' => $title,
            'deleted_by_id' => $attributes['deleted_by'] ?? null,
            'deleted_by_name' => null,
            'deleted_at' => $deletedAt?->toISOString(),
            'deleted_at_display' => $deletedAt?->format('M d, Y H:i'),
            'deleted_at_timestamp' => $deletedAt?->timestamp ?? 0,
            'days_remaining' => $daysRemaining,
            'force_delete_at' => $forceDeleteAt?->toISOString(),
            'fields' => $fields,
            'metadata' => $metadata,
            'dependency_warning' => $dependencyWarning,
            'search_blob' => Str::lower(implode(' ', array_filter([
                $module,
                $entry['label'],
                $title,
                (string) $record->getKey(),
                implode(' ', array_map(static function ($value): string {
                    if (is_scalar($value) || $value === null) {
                        return (string) $value;
                    }

                    if ($value instanceof \Stringable) {
                        return (string) $value;
                    }

                    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                }, $attributes)),
            ]))),
        ];
    }

    private function attachHumanReadableReferences(Collection $records): Collection
    {
        $userIds = collect();
        $branchIds = collect();

        foreach ($records as $record) {
            $deletedById = $record['deleted_by_id'] ?? null;
            if (filled($deletedById)) {
                $userIds->push($deletedById);
            }

            foreach ($record['fields'] ?? [] as $field) {
                $key = $field['key'] ?? null;
                $value = $field['value'] ?? null;

                if (! filled($value)) {
                    continue;
                }

                if (in_array($key, ['created_by', 'updated_by', 'deleted_by'], true)) {
                    $userIds->push($value);
                }

                if ($key === 'branch_id') {
                    $branchIds->push($value);
                }
            }
        }

        $users = $userIds->filter()->unique()->isEmpty()
            ? collect()
            : User::withoutGlobalScopes()
                ->whereIn('id', $userIds->filter()->unique()->values()->all())
                ->get(['id', 'name', 'email'])
                ->keyBy('id');

        $branches = $branchIds->filter()->unique()->isEmpty()
            ? collect()
            : Branch::withoutGlobalScopes()
                ->whereIn('id', $branchIds->filter()->unique()->values()->all())
                ->get(['id', 'name'])
                ->keyBy('id');

        return $records->map(function (array $record) use ($users, $branches): array {
            $deletedById = $record['deleted_by_id'] ?? null;
            $deletedByName = $deletedById && isset($users[$deletedById])
                ? ($users[$deletedById]->name ?: $users[$deletedById]->email)
                : 'System';

            $record['deleted_by_name'] = $deletedByName;

            foreach ($record['metadata'] ?? [] as $index => $metadata) {
                if (($metadata['key'] ?? null) === 'deleted_by') {
                    $record['metadata'][$index]['display_value'] = $deletedByName;
                }
            }

            foreach ($record['fields'] ?? [] as $index => $field) {
                $key = $field['key'] ?? null;
                $value = $field['value'] ?? null;

                if (! filled($value)) {
                    continue;
                }

                if (in_array($key, ['created_by', 'updated_by', 'deleted_by'], true) && isset($users[$value])) {
                    $record['fields'][$index]['display_value'] = $users[$value]->name ?: $users[$value]->email;
                }

                if ($key === 'branch_id' && isset($branches[$value])) {
                    $record['fields'][$index]['display_value'] = $branches[$value]->name;
                }
            }

            return $record;
        });
    }

    private function fallbackTitle(Model $record): string
    {
        foreach (['name', 'number', 'code', 'remarks', 'remark', 'description', 'quantity', 'narration'] as $field) {
            $value = $record->getAttribute($field);
            if (filled($value)) {
                return (string) $value;
            }
        }

        return (string) $record->getKey();
    }

    /**
     * Return module options with counts.
     */
    private function moduleOptions(Collection $records): Collection
    {
        $grouped = $records->groupBy('module')->map->count();

        return collect([
            [
                'value' => 'all',
                'label' => 'All modules',
                'count' => $records->count(),
            ],
        ])->merge(
            collect($this->registry())->map(function (array $entry, string $module) use ($grouped): array {
                return [
                    'value' => $module,
                    'label' => $entry['label'],
                    'count' => (int) ($grouped[$module] ?? 0),
                ];
            })->values()
        );
    }

    private function runRestoreStrategy(Model $record, array $entry): void
    {
        if (isset($entry['restore']) && is_callable($entry['restore'])) {
            ($entry['restore'])($record);

            return;
        }

        $record->restore();
    }

    private function runForceDeleteStrategy(Model $record, array $entry): void
    {
        if (isset($entry['force_delete']) && is_callable($entry['force_delete'])) {
            ($entry['force_delete'])($record);

            return;
        }

        $record->forceDelete();
    }

    private function restoreSimpleRelations(Model $record, array $relations): void
    {
        $record->restore();

        foreach ($relations as $relation) {
            if (!method_exists($record, $relation)) {
                continue;
            }

            $related = $record->{$relation}();
            if (method_exists($related, 'withTrashed')) {
                $related->withTrashed()->restore();
            }
        }
    }

    private function forceDeleteSimpleRelations(Model $record, array $relations): void
    {
        foreach ($relations as $relation) {
            if (!method_exists($record, $relation)) {
                continue;
            }

            $related = $record->{$relation}();
            if (method_exists($related, 'withTrashed')) {
                $related->withTrashed()->forceDelete();
            }
        }

        $record->forceDelete();
    }

    private function restoreTransactionRecord(Model $record, array $relations = []): void
    {
        $record->restore();
        $this->restoreSimpleRelations($record, $relations);
        $this->restoreTransaction($record);
    }

    private function forceDeleteTransactionRecord(Model $record, array $relations = []): void
    {
        $this->forceDeleteSimpleRelations($record, $relations);
        $this->forceDeleteTransaction($record);
    }

    private function restoreItemRecord(Item $item): void
    {
        $item->restore();
        $item->stocks()->withTrashed()->restore();
        $item->stockBalances()->withTrashed()->restore();
        $this->restoreOpeningTransaction($item);
    }

    private function forceDeleteItemRecord(Item $item): void
    {
        $item->stocks()->withTrashed()->forceDelete();
        $item->stockBalances()->withTrashed()->forceDelete();
        $this->forceDeleteOpeningTransaction($item);
        $item->forceDelete();
    }

    private function restoreOpeningRecord(Model $record): void
    {
        $record->restore();
        $this->restoreOpeningTransaction($record);
    }

    private function forceDeleteOpeningRecord(Model $record): void
    {
        $this->forceDeleteOpeningTransaction($record);
        $record->forceDelete();
    }

    private function restoreLedgerOpeningRecord(Model $record): void
    {
        $record->restore();

        if (!method_exists($record, 'opening')) {
            return;
        }

        $opening = $record->opening()->withTrashed()->first();
        if (! $opening) {
            return;
        }

        $transactionId = $opening->transaction_id ?? null;
        if ($transactionId) {
            Transaction::withTrashed()->where('id', $transactionId)->restore();
            TransactionLine::withTrashed()->where('transaction_id', $transactionId)->restore();
        }

        $opening->restore();
    }

    private function forceDeleteLedgerOpeningRecord(Model $record): void
    {
        if (method_exists($record, 'opening')) {
            $opening = $record->opening()->withTrashed()->first();
            if ($opening) {
                $transactionId = $opening->transaction_id ?? null;
                if ($transactionId) {
                    TransactionLine::withTrashed()->where('transaction_id', $transactionId)->forceDelete();
                    Transaction::withTrashed()->where('id', $transactionId)->forceDelete();
                }

                $opening->forceDelete();
            }
        }

        $record->forceDelete();
    }

    private function restoreTransaction(Model $record): void
    {
        if (!method_exists($record, 'transaction')) {
            return;
        }

        $transaction = $record->transaction()->withTrashed()->first();
        if (! $transaction) {
            return;
        }

        $transaction->restore();

        if (method_exists($transaction, 'lines')) {
            $transaction->lines()->withTrashed()->restore();
        }
    }

    private function forceDeleteTransaction(Model $record): void
    {
        if (!method_exists($record, 'transaction')) {
            return;
        }

        $transaction = $record->transaction()->withTrashed()->first();
        if (! $transaction) {
            return;
        }

        if (method_exists($transaction, 'lines')) {
            $transaction->lines()->withTrashed()->forceDelete();
        }

        $transaction->forceDelete();
    }

    private function restoreOpeningTransaction(Model $record): void
    {
        if (!method_exists($record, 'openingTransaction')) {
            return;
        }

        $openingTransaction = $record->openingTransaction()->withTrashed()->first();
        if (! $openingTransaction) {
            return;
        }

        if (method_exists($openingTransaction, 'lines')) {
            $openingTransaction->lines()->withTrashed()->restore();
        }

        $openingTransaction->restore();
    }

    private function forceDeleteOpeningTransaction(Model $record): void
    {
        if (!method_exists($record, 'openingTransaction')) {
            return;
        }

        $openingTransaction = $record->openingTransaction()->withTrashed()->first();
        if (! $openingTransaction) {
            return;
        }

        if (method_exists($openingTransaction, 'lines')) {
            $openingTransaction->lines()->withTrashed()->forceDelete();
        }

        $openingTransaction->forceDelete();
    }
}

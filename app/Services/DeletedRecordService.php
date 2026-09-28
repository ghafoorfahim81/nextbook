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
use App\Models\Administration\CustomerGroup;
use App\Models\Administration\Department;
use App\Models\Administration\Designation;
use App\Models\Administration\LandedCostCategory;
use App\Models\Administration\PaymentTerm;
use App\Models\Administration\Quantity;
use App\Models\Administration\Size;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseCategory;
use App\Models\Expense\ExpenseDetail;
use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeContract;
use App\Models\Hr\EmployeeDocument;
use App\Models\Hr\EmployeeLoan;
use App\Models\Hr\Holiday;
use App\Models\Hr\Interview;
use App\Models\Hr\JobApplication;
use App\Models\Hr\JobOpening;
use App\Models\Hr\LeaveAllocation;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Payroll;
use App\Models\Hr\SalaryComponent;
use App\Models\Hr\SalaryPayment;
use App\Models\Hr\SalaryStructure;
use App\Models\Hr\Shift;
use App\Models\Hr\TaxBracketSet;
use App\Models\Inventory\DiscountRule;
use App\Models\Inventory\Item;
use App\Models\Inventory\LandedCost;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\ItemTransfer\ItemTransfer;
use App\Models\JournalEntry\JournalClass;
use App\Models\JournalEntry\JournalEntry;
use App\Models\Ledger\Ledger;
use App\Models\Owner\Drawing;
use App\Models\Owner\Owner;
use App\Models\Payment\Payment;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseQuotation;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Receipt\Receipt;
use App\Models\Sale\InvoiceFormat;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleQuotation;
use App\Models\Sale\SaleReturn;
use App\Models\Transaction\Transaction;
use App\Models\Role;
use App\Models\Transaction\TransactionLine;
use App\Models\User;
use App\Services\Hr\SalaryDisbursementService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

        // Two passes on purpose. The first builds only what filtering, sorting
        // and the summary cards need; the second expands the page the user is
        // actually looking at.
        //
        // Everything used to be expanded up front, and the expensive parts are
        // per record: getDependencyMessage() runs a count() for every relation
        // a model declares, and the field list copies every column. On a trash
        // with a few hundred rows that was hundreds of queries and a full
        // attribute copy per row, to render twenty-five of them.
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

        $total = $records->count();
        $offset = ($page - 1) * $perPage;

        $pageRows = $records->slice($offset, $perPage)->values();
        $blockingParents = $this->resolveTrashedParents($pageRows->pluck('model'));

        $items = $this->attachHumanReadableReferences(
            $pageRows->map(fn (array $record) => $this->expandRecord($record, $blockingParents))
        );

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
                // Modules actually represented in the result, which is what the
                // card says. It used to count the registry instead, so the page
                // read "55 modules represented" over an empty table.
                'modules' => $records->pluck('module')->unique()->count(),
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

        $this->guardTrashedParents($record);

        $this->runRestoreStrategy($record, $entry);

        return $record->fresh();
    }

    /**
     * Refuse a restore that would bring a record back pointing at a parent that
     * is itself still in the trash.
     *
     * Restoring a sale whose customer is deleted leaves an invoice attached to
     * a ledger that no longer exists: it shows on the sales list, its lines are
     * in the general ledger, and the party it belongs to cannot be opened. In
     * an accounting system that is worse than refusing, and the way out is
     * always the same — restore the parent first — so the message says which.
     */
    private function guardTrashedParents(Model $record): void
    {
        $parents = $this->trashedParentsOf($record);

        if ($parents === []) {
            return;
        }

        throw ValidationException::withMessages([
            'record' => __('general.restore_blocked_by_trashed_parents', [
                'parents' => collect($parents)
                    ->map(fn (array $parent) => trim($parent['label'].' '.$parent['title']))
                    ->implode(', '),
            ]),
        ]);
    }

    /**
     * The record's belongs-to parents that are themselves soft deleted.
     *
     * Read off the model rather than configured per module: every model already
     * declares its parents as `public function x(): BelongsTo`, and a registry
     * of sixty modules would drift from them within a release. Relations whose
     * parent does not soft delete cannot be trashed, so they are skipped.
     *
     * @return list<array{relation: string, label: string, title: string}>
     */
    private function trashedParentsOf(Model $record): array
    {
        return $this->resolveTrashedParents(collect([$record]))[$this->modelKey($record)] ?? [];
    }

    /**
     * The same answer for a whole page, in a handful of queries.
     *
     * Asked record by record this is one SELECT per relation per row — about a
     * hundred for a page of twenty-five, which is the cost the two-pass split
     * above exists to avoid. Parents are collected first and fetched one query
     * per parent table instead.
     *
     * @param  Collection<int, Model>  $records
     * @return array<string, list<array{relation: string, label: string, title: string}>>
     */
    private function resolveTrashedParents(Collection $records): array
    {
        /** @var array<string, list<array{relation: string, class: class-string<Model>, id: string}>> $plan */
        $plan = [];
        /** @var array<class-string<Model>, list<string>> $wanted */
        $wanted = [];

        foreach ($records as $record) {
            $key = $this->modelKey($record);
            $plan[$key] = [];

            foreach ($this->belongsToRelationsOf($record) as $relation) {
                $foreignKey = $record->getAttribute($relation['foreign_key']);

                if (blank($foreignKey)) {
                    continue;
                }

                $plan[$key][] = [
                    'relation' => $relation['name'],
                    'class' => $relation['class'],
                    'id' => (string) $foreignKey,
                ];

                $wanted[$relation['class']][] = (string) $foreignKey;
            }
        }

        /** @var array<class-string<Model>, Collection<string, Model>> $parents */
        $parents = [];

        foreach ($wanted as $class => $ids) {
            // withoutGlobalScopes so a parent on another branch is still seen:
            // a parent that is merely out of scope is not a parent that is gone,
            // and treating it as absent would let the orphan through.
            $parents[$class] = $class::query()
                ->withoutGlobalScopes()
                ->withTrashed()
                ->whereIn((new $class)->getKeyName(), array_values(array_unique($ids)))
                ->get()
                ->keyBy(fn (Model $parent) => (string) $parent->getKey());
        }

        $blocking = [];

        foreach ($plan as $key => $relations) {
            $blocking[$key] = [];

            foreach ($relations as $relation) {
                $parent = $parents[$relation['class']][$relation['id']] ?? null;

                if (! $parent || ! $parent->trashed()) {
                    continue;
                }

                $blocking[$key][] = [
                    'relation' => $relation['relation'],
                    'label' => $this->moduleLabelForModel($parent::class) ?? Str::headline($relation['relation']),
                    'title' => $this->fallbackTitle($parent),
                ];
            }
        }

        return $blocking;
    }

    /**
     * A model's soft-deleting belongs-to parents, read off its own declarations.
     *
     * Every model already writes `public function x(): BelongsTo`, so this stays
     * true as relations are added; a hand-kept list across sixty modules would
     * not. Cached per class because a page is mostly rows of the same module and
     * the reflection is the expensive half.
     *
     * @return list<array{name: string, class: class-string<Model>, foreign_key: string}>
     */
    private function belongsToRelationsOf(Model $record): array
    {
        static $cache = [];

        $class = $record::class;

        if (isset($cache[$class])) {
            return $cache[$class];
        }

        $relations = [];

        foreach ((new \ReflectionClass($record))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfParameters() > 0) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (! $returnType instanceof \ReflectionNamedType || $returnType->getName() !== BelongsTo::class) {
                continue;
            }

            try {
                /** @var BelongsTo $relation */
                $relation = $record->{$method->getName()}();
                $related = $relation->getRelated();

                // A parent that cannot be soft deleted cannot be in the trash.
                if (! $this->usesSoftDeletes($related)) {
                    continue;
                }

                $relations[] = [
                    'name' => $method->getName(),
                    'class' => $related::class,
                    'foreign_key' => $relation->getForeignKeyName(),
                ];
            } catch (Throwable) {
                // A relation we cannot build says nothing about whether its
                // parent is trashed, so it must never block a restore.
                continue;
            }
        }

        return $cache[$class] = $relations;
    }

    private function modelKey(Model $record): string
    {
        return $record::class.':'.$record->getKey();
    }

    private function usesSoftDeletes(Model $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }

    /**
     * The registry's own name for a model, so the message calls a parent what
     * the trash screen calls it.
     */
    private function moduleLabelForModel(string $class): ?string
    {
        foreach ($this->registry() as $entry) {
            if ($entry['model'] === $class && ($entry['listed'] ?? true) !== false) {
                return $entry['label'];
            }
        }

        return null;
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
            // Unlisted aliases point at a table a listed module already sweeps
            // (`ledgers` is customers + suppliers), so skipping them here only
            // avoids visiting the same rows twice.
            if (($entry['listed'] ?? true) === false) {
                continue;
            }

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
            // Events are suppressed here, so the HasAttachments forceDeleted
            // hook won't fire — purge attachments explicitly.
            if (method_exists($record, 'attachments')) {
                app(AttachmentService::class)->purge($record);
            }

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
                'title' => fn (Model $record) => $record->localized_name,
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
            'landed_cost_categories' => [
                'label' => 'Landed Cost Categories',
                'model' => LandedCostCategory::class,
                'title' => fn (Model $record) => $record->localized_name,
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
                // Commercial parties only. An employee's ledger is an internal
                // half of their HR record, restored by restoring the employee —
                // surfacing it here would let someone with ledger rights bring
                // back a deleted employee's payable account on its own.
                'query' => fn (Builder $query) => $query->whereIn('type', LedgerType::commercialValues()),
                // Not listed, only resolvable by key. Commercial types are
                // exactly customers + suppliers, both of which have their own
                // entry below, so listing this one showed every deleted party
                // twice and counted it twice in the totals. LedgerController
                // still force-deletes through this key, so it has to stay.
                'listed' => false,
                'restore' => fn (Model $record) => $this->restoreLedgerOpeningRecord($record),
                'force_delete' => fn (Model $record) => $this->forceDeleteLedgerOpeningRecord($record),
            ],
            'employees' => [
                'label' => 'Employees',
                'model' => Employee::class,
                'title' => fn (Model $record) => trim(($record->code ? $record->code.' - ' : '').($record->full_name ?? '')),
                // Restore and force-delete both go through the model so
                // EmployeeObserver can carry the companion ledger with it.
                'restore' => fn (Model $record) => $record->restore(),
                'force_delete' => fn (Model $record) => $record->forceDelete(),
            ],
            'employee_contracts' => [
                'label' => 'Employee contracts',
                'model' => EmployeeContract::class,
                'title' => fn (Model $record) => trim((string) $record->contract_number),
            ],
            'employee_documents' => [
                'label' => 'Employee documents',
                'model' => EmployeeDocument::class,
                'title' => fn (Model $record) => trim((string) ($record->document_number ?: $record->document_type?->value)),
            ],
            'shifts' => [
                'label' => 'Shifts',
                'model' => Shift::class,
                'title' => fn (Model $record) => trim(($record->code ? $record->code.' - ' : '').($record->name ?? '')),
            ],
            'holidays' => [
                'label' => 'Holidays',
                'model' => Holiday::class,
                'title' => fn (Model $record) => trim((string) $record->name),
            ],
            'attendance_devices' => [
                'label' => 'Attendance devices',
                'model' => AttendanceDevice::class,
                'title' => fn (Model $record) => trim(($record->code ? $record->code.' - ' : '').($record->name ?? '')),
            ],
            'leave_types' => [
                'label' => 'Leave types',
                'model' => LeaveType::class,
                'title' => fn (Model $record) => trim(($record->code ? $record->code.' - ' : '').($record->name ?? '')),
            ],
            'leave_allocations' => [
                'label' => 'Leave allocations',
                'model' => LeaveAllocation::class,
                'title' => fn (Model $record) => trim(($record->employee?->full_name ?? '').' — '.($record->leaveType?->name ?? '')),
            ],
            'leave_requests' => [
                'label' => 'Leave requests',
                'model' => LeaveRequest::class,
                'title' => fn (Model $record) => trim('#'.($record->number ?? '').' '.($record->employee?->full_name ?? '')),
            ],
            // `attendances` is deliberately absent: at roughly 150k rows a year
            // per branch the trash listing would be unusable, and a deleted day
            // is recreated by re-running the roster or the pairer.
            'salary_components' => [
                'label' => 'Salary components',
                'model' => SalaryComponent::class,
                'title' => fn (Model $record) => trim(($record->code ? $record->code.' - ' : '').($record->name ?? '')),
            ],
            'salary_structures' => [
                'label' => 'Salary structures',
                'model' => SalaryStructure::class,
                'title' => fn (Model $record) => trim(($record->employee?->full_name ?? $record->name ?? '')),
            ],
            'tax_bracket_sets' => [
                'label' => 'Tax tables',
                'model' => TaxBracketSet::class,
                'title' => fn (Model $record) => trim((string) $record->name),
            ],
            'payrolls' => [
                'label' => 'Payroll runs',
                'model' => Payroll::class,
                'title' => fn (Model $record) => trim('#'.($record->number ?? '').' '.($record->period_label ?? '')),
            ],
            'salary_payments' => [
                'label' => 'Salary payments',
                'model' => SalaryPayment::class,
                'title' => fn (Model $record) => trim('#'.($record->number ?? '').' '.($record->employee?->full_name ?? '')),
                // Both sides go through the service so the voucher and its
                // settlements come back with the payment — restoring the row
                // alone would show a payment the general ledger has no record
                // of, and leave the payslip looking unpaid.
                'restore' => fn (Model $record) => app(SalaryDisbursementService::class)->restore($record),
            ],
            'employee_loans' => [
                'label' => 'Employee loans',
                'model' => EmployeeLoan::class,
                'title' => fn (Model $record) => trim('#'.($record->number ?? '').' '.($record->employee?->full_name ?? '')),
            ],
            'job_openings' => [
                'label' => 'Job openings',
                'model' => JobOpening::class,
                'title' => fn (Model $record) => trim(($record->code ? $record->code.' - ' : '').($record->title ?? '')),
            ],
            'job_applications' => [
                'label' => 'Job applications',
                'model' => JobApplication::class,
                'title' => fn (Model $record) => trim(($record->application_number ? $record->application_number.' - ' : '').($record->full_name ?? '')),
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
            'discount_rules' => [
                'label' => 'Discount Rules',
                'model' => DiscountRule::class,
                'title' => fn (Model $record) => trim((string) $record->name),
            ],
            'customer_groups' => [
                'label' => 'Customer Groups',
                'model' => CustomerGroup::class,
                'title' => fn (Model $record) => $record->localized_name,
            ],
            'payment_terms' => [
                'label' => 'Payment Terms',
                'model' => PaymentTerm::class,
                'title' => fn (Model $record) => trim((string) $record->name),
            ],
            'invoice_formats' => [
                'label' => 'Invoice Formats',
                'model' => InvoiceFormat::class,
                'title' => fn (Model $record) => trim(($record->code ? $record->code.' - ' : '').($record->name ?? '')),
                // This model carries company_id rather than branch_id and has no
                // global scope, so without this every company's formats would
                // show in every company's trash. Unfiltered when there is no
                // user to scope to — the same choice BranchSpecific makes, and
                // the reason the cleanup job can still reach these rows rather
                // than matching `company_id is null` and purging nothing.
                'query' => fn (Builder $query) => ($companyId = auth()->user()?->company_id)
                    ? $query->where('company_id', $companyId)
                    : $query,
            ],
            'interviews' => [
                'label' => 'Interviews',
                'model' => Interview::class,
                'title' => fn (Model $record) => trim(
                    ($record->application?->full_name ?? '').($record->round ? ' — round '.$record->round : '')
                ),
                'restore' => fn (Model $record) => $this->restoreSimpleRelations($record, ['panelists']),
                'force_delete' => fn (Model $record) => $this->forceDeleteSimpleRelations($record, ['panelists']),
            ],
            'stock_adjustments' => [
                'label' => 'Stock Adjustments',
                'model' => StockAdjustment::class,
                'title' => fn (Model $record) => $record->reference ?: $record->date?->format('Y-m-d') ?: $record->id,
                // Only drafts can be deleted, and deleting one releases the
                // stock it had reserved. Restoring brings the document and its
                // lines back as a draft; the reservation is re-taken when the
                // draft is posted, which is the same path a new draft follows.
                'restore' => fn (Model $record) => $this->restoreSimpleRelations($record, ['items']),
                'force_delete' => fn (Model $record) => $this->forceDeleteSimpleRelations($record, ['items']),
            ],
            'landed_costs' => [
                'label' => 'Landed Costs',
                'model' => LandedCost::class,
                'title' => fn (Model $record) => $record->reference ?: $record->date?->format('Y-m-d') ?: $record->id,
                // Deleting one soft-deletes its voucher, the voucher's lines,
                // its allocations and its lines, so all four have to come back
                // together or the document returns without its accounting half.
                'restore' => fn (Model $record) => $this->restoreTransactionRecord($record, relations: ['items', 'categoryAllocations']),
                'force_delete' => fn (Model $record) => $this->forceDeleteTransactionRecord($record, relations: ['items', 'categoryAllocations']),
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
            'purchase_orders' => [
                'label' => 'Purchase Orders',
                'model' => PurchaseOrder::class,
                'title' => fn (Model $record) => $record->number ?: $record->note ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreSimpleRelations($record, ['items']),
                'force_delete' => fn (Model $record) => $this->forceDeleteSimpleRelations($record, ['items']),
            ],
            'purchase_returns' => [
                'label' => 'Purchase Returns',
                'model' => PurchaseReturn::class,
                'title' => fn (Model $record) => $record->number ?: $record->description ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreTransactionRecord($record, relations: ['items']),
                'force_delete' => fn (Model $record) => $this->forceDeleteTransactionRecord($record, relations: ['items']),
            ],
            'sale_returns' => [
                'label' => 'Sale Returns',
                'model' => SaleReturn::class,
                'title' => fn (Model $record) => $record->number ?: $record->description ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreTransactionRecord($record, relations: ['items']),
                'force_delete' => fn (Model $record) => $this->forceDeleteTransactionRecord($record, relations: ['items']),
            ],
            'sale_orders' => [
                'label' => 'Sale Orders',
                'model' => SaleOrder::class,
                'title' => fn (Model $record) => $record->number ?: $record->note ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreSimpleRelations($record, ['items']),
                'force_delete' => fn (Model $record) => $this->forceDeleteSimpleRelations($record, ['items']),
            ],
            'purchase_quotations' => [
                'label' => 'Purchase Quotations',
                'model' => PurchaseQuotation::class,
                'title' => fn (Model $record) => $record->number ?: $record->note ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreSimpleRelations($record, ['items']),
                'force_delete' => fn (Model $record) => $this->forceDeleteSimpleRelations($record, ['items']),
            ],
            'sale_quotations' => [
                'label' => 'Sale Quotations',
                'model' => SaleQuotation::class,
                'title' => fn (Model $record) => $record->number ?: $record->note ?: $record->id,
                'restore' => fn (Model $record) => $this->restoreSimpleRelations($record, ['items']),
                'force_delete' => fn (Model $record) => $this->forceDeleteSimpleRelations($record, ['items']),
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
            'contra_settlements' => [
                'label' => 'Set-offs',
                'model' => \App\Models\Accounting\ContraSettlement::class,
                'title' => fn (Model $record) => $record->number ?: $record->narration ?: $record->id,
                // Two vouchers, not one, so the generic transaction helpers do
                // not apply: a set-off relieves the customer on one and the
                // supplier on the other.
                'restore' => fn (Model $record) => $this->restoreContraSettlement($record),
                'force_delete' => fn (Model $record) => $this->forceDeleteContraSettlement($record),
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
            'roles' => [
                'label' => 'Roles',
                'model' => Role::class,
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
            if (($entry['listed'] ?? true) === false) {
                continue;
            }

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
        // Carbon 3 returns diffInDays SIGNED: now()->diffInDays($past) is
        // negative, so subtracting it ADDED to the window. A record deleted
        // five days ago reported 35 days remaining instead of 25, the counter
        // climbed instead of running down, and "expiring within 7 days" could
        // never fire. Measure from the deletion forward and subtract.
        $daysElapsed = $deletedAt
            ? (int) floor($deletedAt->copy()->startOfDay()->diffInDays(now()->startOfDay()))
            : 0;
        $daysRemaining = $deletedAt
            ? (int) max(0, self::RETENTION_DAYS - $daysElapsed)
            : self::RETENTION_DAYS;
        $forceDeleteAt = $deletedAt?->copy()->addDays(self::RETENTION_DAYS);

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
            // Carried, not serialised. expandRecord() reads these for the page
            // the user is on and drops them; none of them reach Inertia.
            'model' => $record,
            'deleted_at_string' => $deletedAt?->toDateTimeString(),
            'force_delete_at_string' => $forceDeleteAt?->toDateTimeString(),
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

    /**
     * Expand one listed row into what the details dialog needs.
     *
     * Only ever called for the current page. The field list copies every
     * column, and getDependencyMessage() runs a count() query per relation the
     * model declares — doing that for the whole trash to render twenty-five
     * rows was the bulk of this screen's cost.
     *
     * @param  array<string, mixed>  $record
     * @param  array<string, list<array{relation: string, label: string, title: string}>>  $blockingParents
     * @return array<string, mixed>
     */
    private function expandRecord(array $record, array $blockingParents = []): array
    {
        /** @var Model $model */
        $model = $record['model'];
        $attributes = $model->getAttributes();

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

        $record['fields'] = $fields;
        $record['metadata'] = [
            [
                'key' => 'deleted_by',
                'label' => 'Deleted By',
                'value' => $attributes['deleted_by'] ?? null,
                'display_value' => null,
            ],
            [
                'key' => 'deleted_at',
                'label' => 'Deleted At',
                'value' => $record['deleted_at_string'],
                'display_value' => null,
            ],
            [
                'key' => 'auto_force_delete_at',
                'label' => 'Auto Force Delete At',
                'value' => $record['force_delete_at_string'],
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
        if (method_exists($model, 'getDependencyMessage')) {
            try {
                $dependencyWarning = $model->getDependencyMessage();
            } catch (Throwable) {
                $dependencyWarning = null;
            }
        }

        $record['dependency_warning'] = $dependencyWarning;
        // What a restore would be refused on, said before the button is pressed.
        $record['blocking_parents'] = $blockingParents[$this->modelKey($model)] ?? [];

        unset(
            $record['model'],
            $record['search_blob'],
            $record['deleted_at_string'],
            $record['force_delete_at_string'],
        );

        return $record;
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
            collect($this->registry())
                ->filter(fn (array $entry) => ($entry['listed'] ?? true) !== false)
                ->map(function (array $entry, string $module) use ($grouped): array {
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

    /**
     * Does this relation's model soft-delete?
     *
     * This used to be asked as method_exists($relation, 'withTrashed'), which
     * is always false: withTrashed is a macro the soft-delete scope adds to the
     * query builder and reaches the relation through __call, so method_exists
     * never sees it. Both helpers below therefore skipped every child silently
     * — force-delete left the lines behind and then died on the foreign key,
     * and restore brought a document back with no lines at all.
     */
    private function relationSoftDeletes(Relation $relation): bool
    {
        return in_array(
            SoftDeletes::class,
            class_uses_recursive($relation->getRelated()),
            true,
        );
    }

    private function restoreSimpleRelations(Model $record, array $relations): void
    {
        $record->restore();

        foreach ($relations as $relation) {
            if (!method_exists($record, $relation)) {
                continue;
            }

            $related = $record->{$relation}();

            // Only a soft-deleted child can be brought back; a hard-deleted one
            // is already gone.
            if ($this->relationSoftDeletes($related)) {
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

            // Children first, or the parent's delete hits their foreign key.
            if ($this->relationSoftDeletes($related)) {
                $related->withTrashed()->forceDelete();
            } else {
                $related->delete();
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

    /**
     * Both halves of a set-off come back together, or the clearing account is
     * left holding one side of an offset with nothing to cancel it.
     */
    private function restoreContraSettlement(Model $record): void
    {
        $record->restore();

        foreach ($record->transactionIds() as $transactionId) {
            $transaction = \App\Models\Transaction\Transaction::withTrashed()->find($transactionId);

            if (! $transaction) {
                continue;
            }

            $transaction->restore();
            $transaction->lines()->withTrashed()->restore();

            \App\Models\Accounting\Settlement::withoutGlobalScopes()
                ->onlyTrashed()
                ->where('transaction_id', $transactionId)
                ->restore();
        }
    }

    private function forceDeleteContraSettlement(Model $record): void
    {
        foreach ($record->transactionIds() as $transactionId) {
            \App\Models\Accounting\Settlement::withoutGlobalScopes()
                ->withTrashed()
                ->where('transaction_id', $transactionId)
                ->forceDelete();

            $transaction = \App\Models\Transaction\Transaction::withTrashed()->find($transactionId);

            if (! $transaction) {
                continue;
            }

            $transaction->lines()->withTrashed()->forceDelete();
            $transaction->forceDelete();
        }

        $record->forceDelete();
    }

    private function restoreItemRecord(Item $item): void
    {
        $item->restore();
        // ItemController::destroy soft-deletes the variants with the item, so
        // they have to come back with it too — an item with no variants has
        // nothing sellable attached and no bucket for its stock.
        $item->variants()->withTrashed()->restore();
        $item->stocks()->withTrashed()->restore();
        $item->stockBalances()->withTrashed()->restore();
        $this->restoreOpeningTransaction($item);
    }

    private function forceDeleteItemRecord(Item $item): void
    {
        // Children first, or the item's delete hits their foreign key.
        $item->variants()->withTrashed()->forceDelete();
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

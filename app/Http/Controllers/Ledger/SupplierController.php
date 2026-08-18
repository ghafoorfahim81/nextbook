<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BuildsLedgerStatement;
use App\Http\Requests\Ledger\LedgerStoreRequest;
use App\Http\Requests\Ledger\LedgerUpdateRequest;
use App\Http\Resources\Ledger\LedgerResource;
use App\Http\Resources\Transaction\TransactionResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Administration\BranchResource;
use App\Http\Resources\Receipt\ReceiptResource;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use App\Models\Administration\Currency;
use App\Models\Administration\Branch;
use App\Models\Administration\Country;
use App\Models\Administration\CustomerGroup;
use App\Models\Administration\PaymentTerm;
use App\Models\Administration\Province;
use App\Http\Resources\Purchase\PurchaseResource;
use App\Http\Resources\Payment\PaymentResource;
use Illuminate\Http\Request;
use App\Services\Accounting\PaymentStatusService;
use App\Services\Accounting\SettlementService;
use App\Services\AttachmentService;
use App\Services\DateConversionService;
use App\Services\LedgerOpeningService;
use App\Services\LedgerStatementService;
use App\Services\TransactionService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Http\Resources\Sale\SaleResource;
use App\Models\Ledger\LedgerTransaction;
use App\Models\Transaction\TransactionLine;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction\Transaction;
use App\Support\Inertia\CacheKey;
use App\Models\User;
use Illuminate\Support\Str;
use App\Services\SpreadsheetExportService;
use App\Services\PdfExportService;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SupplierController extends Controller
{
    use BuildsLedgerStatement;

    public function __construct()
    {
        $this->authorizeResource(Ledger::class, 'supplier');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $type = $request->input('type', 'supplier'); // default to supplier

        $suppliers = Ledger::search($request->query('search'))
            ->where('type', $type) // Filter by type
            ->filter($filters)
            ->with(['currency', 'branch', 'group', 'country', 'province'])
            // Feeds the `statement` accessor so the list doesn't run one aggregate
            // query per row.
            ->withStatementTotals()
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Ledgers/Suppliers/Index', [
            'suppliers' => LedgerResource::collection($suppliers),
            'filterOptions' => [
                'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name']),
                'groups' => CustomerGroup::query()->orderBy('name_en')->get(['id', 'name_en', 'name_fa']),
                'countries' => Country::query()->orderBy('name_en')->get(['id', 'name_en', 'name_fa']),
                'provinces' => Province::query()->orderBy('name_en')->get(['id', 'name_en', 'name_fa', 'country_id']),
                'users' => User::query()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $sortField,
                'sortDirection' => $sortDirection,
                'filters' => $filters,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        return inertia('Ledgers/Suppliers/Create', [
            'currencies' => CurrencyResource::collection(Currency::orderBy('name')->get()),
            'branches' => BranchResource::collection(Branch::orderBy('name')->get()),
            ...$this->referenceData(),
            'nextCode' => $this->nextCode($request->user()?->branch_id),
            'accountTypes' => [],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        LedgerStoreRequest $request,
        LedgerOpeningService $ledgerOpeningService,
        AttachmentService $attachmentService,
    ) {
        $validated = $request->validated();
        $validated['type'] = 'supplier';
        $validated['code'] = $validated['code'] ?: $this->nextCode($request->user()?->branch_id);
        $validated['is_active'] = $validated['is_active'] ?? true;

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('ledgers/photos', 'public');
        }

        $ledger = Ledger::create(Arr::except($validated, ['attachments']));

        if ($request->hasFile('attachments')) {
            $attachmentService->store($ledger, $request->file('attachments'));
        }

        $ledgerOpeningService->sync($ledger, $validated['openings'] ?? []);

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        if ($request->boolean('stay') || $request->boolean('create_and_new')) {
            return to_route('suppliers.create')
                ->with('success', __('general.created_successfully', ['resource' => __('general.resource.supplier')]));
        }

        return to_route('suppliers.index')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.supplier')]));
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Ledger $supplier, LedgerStatementService $statementService)
    {
        // Only the relations the Show page renders — the supplier's whole
        // transaction-line history was eager-loaded before, making high-volume
        // supplier pages very slow.
        $supplier->load([
            'currency',
            'group',
            'paymentTerm',
            'country',
            'province',
            'createdBy',
            'updatedBy',
            'openings',
            'openings.transaction.currency',
            'openings.transaction.lines',
            'attachments',
        ]);

        $lists = $this->transactionLists($supplier);

        $ledgerStatement = $statementService->build($supplier, $this->statementFilters($request));

        // Deliberately unfiltered: the profile card reports the party's standing
        // position, not whatever window the statement tab is currently showing.
        $currencyBalances = $statementService->balancesByCurrency($supplier);

        // Open items are claims, not documents — a bill, an opening balance and
        // a manual journal credit to payables all appear here on equal footing,
        // each with the rate it was booked at.
        $settlements = app(PaymentStatusService::class);
        $openItems = app(SettlementService::class)->openItems($supplier->id);
        $settlementHistory = $settlements->settlementHistoryForLedger($supplier->id);
        $settlementBalances = $settlements->balancesForLedger($supplier->id);

        if ($request->expectsJson()) {
            return response()->json([
                'supplier' => new LedgerResource($supplier),
                ...$lists,
                'ledgerStatement' => $ledgerStatement,
                'currencyBalances' => $currencyBalances,
                'openItems' => $openItems,
                'settlementHistory' => $settlementHistory,
                'settlementBalances' => $settlementBalances,
            ]);
        }

        return inertia('Ledgers/Suppliers/Show', [
            'supplier' => new LedgerResource($supplier),
            ...$lists,
            'ledgerStatement' => $ledgerStatement,
            'currencyBalances' => $currencyBalances,
            'openItems' => $openItems,
            'settlementHistory' => $settlementHistory,
            'settlementBalances' => $settlementBalances,
        ]);
    }

    /**
     * Lightweight purchase/receipt/payment rows for the supplier Show page, avoiding
     * the heavy per-row work full resources do (which the summary tables never use).
     *
     * @return array{purchases: array, receipts: array, payments: array}
     */
    protected function transactionLists(Ledger $ledger): array
    {
        $dateService = app(DateConversionService::class);

        $typeLabel = function ($type) {
            if ($type instanceof \App\Enums\SalePurchaseType) {
                return $type->getLabel();
            }

            return \App\Enums\SalePurchaseType::tryFrom((string) $type)?->getLabel() ?? $type;
        };

        $purchases = $ledger->purchases()
            ->with(['items:id,purchase_id,quantity,unit_price,discount,tax'])
            ->orderByDesc('date')->orderByDesc('id')->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'number' => $p->number,
                'date' => $dateService->toDisplay($p->date),
                'type' => $typeLabel($p->type),
                'amount' => $p->purchaseTotal(),
                'payment_status' => $p->payment_status?->value,
                'payment_status_label' => $p->payment_status?->getLabel(),
                'description' => $p->description,
            ])->all();

        // Receipts and payments no longer carry an amount column - the money
        // lives on the voucher's cash line, so the amount has to be read the
        // same way the resources read it, or the table shows a dash.
        $mapMovement = fn ($m, float $amount) => [
            'id' => $m->id,
            'number' => $m->number,
            'date' => $dateService->toDisplay($m->date),
            'amount' => $amount,
            'currency_code' => $m->transaction?->currency?->code,
            'rate' => $m->transaction?->rate ?? 0,
            'payment_mode' => $m->payment_mode?->value,
            'payment_mode_label' => $m->payment_mode?->getLabel(),
            'narration' => $m->narration,
        ];

        // account.accountType is what cashLine() matches on; without it every
        // row fires its own query to find the cash-or-bank line.
        $movementRelations = ['transaction.currency', 'transaction.lines.account.accountType'];

        $receipts = $ledger->receipts()->with($movementRelations)
            ->orderByDesc('date')->orderByDesc('id')->get()
            ->map(fn ($m) => $mapMovement($m, $m->receivedAmount()))->all();

        $payments = $ledger->payments()->with($movementRelations)
            ->orderByDesc('date')->orderByDesc('id')->get()
            ->map(fn ($m) => $mapMovement($m, $m->paidAmount()))->all();

        return compact('purchases', 'receipts', 'payments');
    }

    /**
     * Lookup lists shared by the supplier create and edit forms.
     */
    protected function referenceData(): array
    {
        return [
            'customerGroups' => CustomerGroup::query()->orderBy('name_en')->get(),
            'paymentTerms' => PaymentTerm::query()->orderBy('name')->get(),
            'countries' => Country::query()->orderBy('name_en')->get(),
            'provinces' => Province::query()->orderBy('name_en')->get(),
        ];
    }

    public function export(
        Request $request,
        Ledger $supplier,
        SpreadsheetExportService $spreadsheetExportService,
        PdfExportService $pdfExportService,
    ): SymfonyResponse {
        $this->authorize('view', $supplier);

        $validated = $request->validate([
            'list' => ['nullable', 'string', Rule::in(['purchases', 'receipts', 'payments', 'statement'])],
            'format' => ['nullable', 'string', Rule::in(['xlsx', 'pdf'])],
        ]);

        $list = $validated['list'] ?? 'purchases';
        $format = $validated['format'] ?? 'xlsx';
        $supplier->loadMissing(['currency', 'branch']);

        $translate = fn (string $group, string $key, string $fallback = '') => $spreadsheetExportService->localeTranslation($group, $key, $fallback);

        $rows = match ($list) {
            'receipts' => $this->exportReceiptRows($supplier),
            'payments' => $this->exportPaymentRows($supplier),
            'statement' => $this->statementExportRows(
                app(LedgerStatementService::class)->build($supplier, $this->statementFilters($request)),
                $translate,
            ),
            default => $this->exportPurchaseRows($supplier),
        };

        $moduleLabel = match ($list) {
            'receipts' => $spreadsheetExportService->localeTranslation('receipt', 'receipts', 'Receipts'),
            'payments' => $spreadsheetExportService->localeTranslation('payment', 'payments', 'Payments'),
            'statement' => $spreadsheetExportService->localeTranslation('report', 'reports.supplier_statement.label', 'Supplier Statement'),
            default => $spreadsheetExportService->localeTranslation('purchase', 'purchases', 'Purchases'),
        };

        // Name the party in the heading rather than repeating the generic
        // "Supplier" label, which the module label already carries.
        $sheetTitle = $moduleLabel . ' ' . $supplier->name;

        $payload = [
            'filename' => Str::slug($supplier->name . '-' . $sheetTitle) . '-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name' => $sheetTitle,
            'sheet_title' => $sheetTitle,
            'title' => $supplier->name . ' - ' . $moduleLabel,
            'company_name' => $this->exportCompanyName($request),
            'exported_on' => now()->format('Y m d'),
            'rtl' => in_array(app()->getLocale(), ['fa', 'ps'], true),
            'include_row_number' => true,
            'row_number_label' => $spreadsheetExportService->localeTranslation('report', 'columns.no', 'No.'),
            'columns' => match ($list) {
                'statement' => $this->statementExportColumns($translate),
                'receipts', 'payments' => [
                    ['key' => 'number', 'label' => $spreadsheetExportService->localeTranslation('general', 'number', 'Number')],
                    ['key' => 'date', 'label' => $spreadsheetExportService->localeTranslation('general', 'date', 'Date')],
                    ['key' => 'amount', 'label' => $spreadsheetExportService->localeTranslation('general', 'amount', 'Amount'), 'type' => 'money', 'align' => 'right'],
                    ['key' => 'currency', 'label' => $spreadsheetExportService->localeTranslation('admin', 'currency.currency', 'Currency')],
                    ['key' => 'rate', 'label' => $spreadsheetExportService->localeTranslation('general', 'rate', 'Rate'), 'type' => 'money', 'align' => 'right'],
                    ['key' => 'payment_mode', 'label' => $spreadsheetExportService->localeTranslation('general', 'payment_method', 'Payment Method')],
                    ['key' => 'description', 'label' => $spreadsheetExportService->localeTranslation('general', 'description', 'Description')],
                ],
                default => [
                    ['key' => 'number', 'label' => $spreadsheetExportService->localeTranslation('general', 'number', 'Number')],
                    ['key' => 'date', 'label' => $spreadsheetExportService->localeTranslation('general', 'date', 'Date')],
                    ['key' => 'type', 'label' => $spreadsheetExportService->localeTranslation('general', 'type', 'Type')],
                    ['key' => 'amount', 'label' => $spreadsheetExportService->localeTranslation('general', 'amount', 'Amount'), 'type' => 'money', 'align' => 'right'],
                    ['key' => 'currency', 'label' => $spreadsheetExportService->localeTranslation('admin', 'currency.currency', 'Currency')],
                    ['key' => 'rate', 'label' => $spreadsheetExportService->localeTranslation('general', 'rate', 'Rate'), 'type' => 'money', 'align' => 'right'],
                    ['key' => 'status', 'label' => $spreadsheetExportService->localeTranslation('general', 'status', 'Status')],
                    ['key' => 'description', 'label' => $spreadsheetExportService->localeTranslation('general', 'description', 'Description')],
                ],
            },
            'rows' => $rows,
        ];

        return $format === 'pdf'
            ? $pdfExportService->download($payload)
            : $spreadsheetExportService->download($payload);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Ledger $supplier)
    {
        $supplier->load([
            'currency', 'group', 'paymentTerm', 'country', 'province',
            'openings', 'openings.transaction.currency', 'openings.transaction.lines',
            'attachments',
        ]);

        return inertia('Ledgers/Suppliers/Edit', [
            'supplier' => new LedgerResource($supplier),
            ...$this->referenceData(),
        ]);
    }

    protected function exportPurchaseRows(Ledger $supplier): array
    {
        $purchases = $supplier->purchases()->with(['transaction.currency', 'items'])->orderBy('date')->orderBy('id')->get();
        $rows = collect(PurchaseResource::collection($purchases)->resolve());

        return $rows->map(function (array $row) {
            return [
                'number' => $row['number'] ?? $row['reference_id'] ?? $row['id'] ?? '-',
                'date' => $row['date'] ?? '-',
                'type' => $row['type'] ?? '-',
                'amount' => $row['amount'] ?? 0,
                'currency' => $row['currency_code'] ?? data_get($row, 'transaction.currency.code') ?? '-',
                'rate' => $row['rate'] ?? '-',
                'status' => $row['payment_status_label'] ?? $row['payment_status'] ?? '-',
                'description' => $row['description'] ?? '-',
            ];
        })->all();
    }

    protected function exportReceiptRows(Ledger $supplier): array
    {
        $receipts = $supplier->receipts()->with(['transaction.currency', 'transaction.lines.account'])->orderBy('date')->orderBy('id')->get();
        $rows = collect(ReceiptResource::collection($receipts)->resolve());

        return $rows->map(function (array $row) {
            return [
                'number' => $row['number'] ?? $row['reference_id'] ?? $row['id'] ?? '-',
                'date' => $row['date'] ?? '-',
                'amount' => $row['amount'] ?? 0,
                'currency' => $row['currency_code'] ?? data_get($row, 'transaction.currency.code') ?? data_get($row, 'transaction.currency.name') ?? '',
                'rate' => $row['rate'] ?? 0,
                'payment_mode' => $row['payment_mode_label'] ?? $row['payment_mode'] ?? '-',
                'description' => $row['narration'] ?? $row['description'] ?? '-',
            ];
        })->all();
    }

    protected function exportPaymentRows(Ledger $supplier): array
    {
        $payments = $supplier->payments()->with(['transaction.currency', 'transaction.lines.account'])->orderBy('date')->orderBy('id')->get();
        $rows = collect(PaymentResource::collection($payments)->resolve());

        return $rows->map(function (array $row) {
            return [
                'number' => $row['number'] ?? $row['reference_id'] ?? $row['id'] ?? '-',
                'date' => $row['date'] ?? '-',
                'amount' => $row['amount'] ?? 0,
                'currency' => $row['currency_code'] ?? data_get($row, 'transaction.currency.code') ?? data_get($row, 'transaction.currency.name') ?? '',
                'rate' => $row['rate'] ?? 0,
                'payment_mode' => $row['payment_mode_label'] ?? $row['payment_mode'] ?? '-',
                'description' => $row['narration'] ?? $row['description'] ?? '-',
            ];
        })->all();
    }

    protected function exportCompanyName(Request $request): string
    {
        $company = data_get($request->user(), 'company');

        if (! $company) {
            return config('app.name');
        }

        return match (app()->getLocale()) {
            'fa' => $company->name_fa ?: $company->name_en ?: $company->abbreviation ?: config('app.name'),
            'ps' => $company->name_pa ?: $company->name_en ?: $company->abbreviation ?: config('app.name'),
            default => $company->name_en ?: $company->abbreviation ?: $company->name_fa ?: $company->name_pa ?: config('app.name'),
        };
    }

    /**
     * Replace just the profile photo, from the inline uploader on the Show page.
     */
    public function updatePhoto(Request $request, Ledger $supplier)
    {
        $this->authorize('update', $supplier);
        abort_unless($supplier->type?->value === 'supplier', 404);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($supplier->photo) {
            Storage::disk('public')->delete($supplier->photo);
        }

        $supplier->update([
            'photo' => $request->file('photo')->store('ledgers/photos', 'public'),
        ]);

        return back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        LedgerUpdateRequest $request,
        Ledger $supplier,
        LedgerOpeningService $ledgerOpeningService,
        AttachmentService $attachmentService,
    ) {
        $validated = $request->validated();
        $validated['is_active'] = $validated['is_active'] ?? true;

        // A form without a new upload posts no `photo` key at all, so the existing
        // one must not be cleared.
        $attributes = Arr::except($validated, ['attachments', 'photo']);

        if ($request->hasFile('photo')) {
            if ($supplier->photo) {
                Storage::disk('public')->delete($supplier->photo);
            }

            $attributes['photo'] = $request->file('photo')->store('ledgers/photos', 'public');
        }

        $supplier->update($attributes);

        if ($request->hasFile('attachments')) {
            $attachmentService->store($supplier, $request->file('attachments'));
        }

        // Openings are rebuilt wholesale rather than diffed: the form always posts
        // the party's complete set, one row per currency.
        $ledgerOpeningService->sync($supplier, $validated['openings'] ?? []);

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        return to_route('suppliers.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.supplier')]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Ledger $supplier)
    {

        $openingTransactionIds = $supplier->openings()->pluck('transaction_id')->filter()->all();

        // Allow delete only when supplier has no transactions OR only opening transactions.
        $hasNonOpeningTransactions = TransactionLine::query()
            ->where('ledger_id', $supplier->id)
            ->when(
                $openingTransactionIds !== [],
                fn ($q) => $q->whereNotIn('transaction_id', $openingTransactionIds),
                fn ($q) => $q // no opening found -> any transaction means blocked
            )
            ->exists();

        if ($hasNonOpeningTransactions) {
            return back()->with('error', __('Cannot delete customer: this customer has transactions. Please remove related transactions first.'));
        }

        DB::transaction(function () use ($supplier, $openingTransactionIds) {
            if ($openingTransactionIds !== []) {
                // Delete the whole opening transaction (both lines) and the opening record.
                TransactionLine::whereIn('transaction_id', $openingTransactionIds)->delete();
                Transaction::whereIn('id', $openingTransactionIds)->delete();
                $supplier->openings()->delete();
            }

            $supplier->delete();
        });
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        return redirect()->route('suppliers.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.supplier')]));
    }
    public function restore(Request $request, Ledger $supplier)
    {
        $openingTransactionIds = $supplier->openings()->withTrashed()->pluck('transaction_id')->filter()->all();

        DB::transaction(function () use ($supplier, $openingTransactionIds) {
            if ($openingTransactionIds !== []) {
                Transaction::withTrashed()->whereIn('id', $openingTransactionIds)->restore();
                TransactionLine::withTrashed()->whereIn('transaction_id', $openingTransactionIds)->restore();
                $supplier->openings()->withTrashed()->restore();
            }

            $supplier->restore();
        });
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        return redirect()->route('suppliers.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.supplier')]));
    }

    public function forceDelete(Request $request, Ledger $supplier)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('suppliers', (string) $supplier->id);

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('suppliers.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.supplier')]));
    }

    public function exportList(Request $request, SpreadsheetExportService $spreadsheetExportService)
    {
        $this->authorize('viewAny', Ledger::class);

        $sortField = $request->input('sortField', 'name');
        $sortDirection = $request->input('sortDirection', 'asc');
        $filters = (array) $request->input('filters', []);

        $suppliers = Ledger::search($request->query('search'))
            ->where('type', 'supplier')
            ->filter($filters)
            ->with(['group', 'paymentTerm', 'country', 'province'])
            // Feeds the statement accessor, so the balance column costs one
            // query for the whole sheet instead of one per row.
            ->withStatementTotals()
            ->orderBy($sortField, $sortDirection)
            ->get();

        $rtl = in_array(app()->getLocale(), ['fa', 'ps'], true);
        $t = fn (string $group, string $key, string $fallback = '') => $spreadsheetExportService->localeTranslation($group, $key, $fallback);

        $rows = $suppliers->map(fn ($s) => [
            'name'            => $s->name ?? '-',
            'code'            => $s->code ?? '-',
            'contact_person'  => $s->contact_person ?? '-',
            'phone_no'        => $s->phone_no ?? '-',
            'whatsapp_number' => $s->whatsapp_number ?? '-',
            'email'           => $s->email ?? '-',
            'address'         => $s->address ?? '-',
            'country'         => $s->country?->localized_name ?? '-',
            'province'        => $s->province?->localized_name ?? '-',
            'group'           => $s->group?->localized_name ?? '-',
            'payment_term'    => $s->paymentTerm?->name ?? '-',
            'discount'        => $s->discount !== null ? (float) $s->discount : '-',
            'credit_limit'    => $s->credit_limit !== null ? (float) $s->credit_limit : '-',
            // The same preference-aware string the list and detail pages show,
            // so an exported balance reads the way the user set it to read.
            'balance'         => (string) $s->statement['balance'],
            'is_active'       => $s->is_active ? $t('general', 'active', 'Active') : $t('general', 'inactive', 'Inactive'),
        ])->all();

        $label = $t('ledger', 'supplier.suppliers', 'Suppliers');

        return $spreadsheetExportService->download([
            'filename'           => 'suppliers-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name'         => $label,
            'sheet_title'        => $label,
            'title'              => $label,
            'company_name'       => $this->exportCompanyName($request),
            'exported_on'        => now()->format('Y m d'),
            'rtl'                => $rtl,
            'include_row_number' => true,
            'row_number_label'   => $t('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'name',            'label' => $t('general', 'name', 'Name'), 'width' => 22],
                ['key' => 'code',            'label' => $t('general', 'code', 'Code'), 'width' => 12],
                ['key' => 'contact_person',  'label' => $t('ledger', 'contact_person', 'Contact Person'), 'width' => 18],
                ['key' => 'phone_no',        'label' => $t('general', 'phone', 'Phone'), 'width' => 14],
                ['key' => 'whatsapp_number', 'label' => $t('ledger', 'whatsapp_number', 'WhatsApp Number'), 'width' => 16],
                ['key' => 'email',           'label' => $t('general', 'email', 'Email'), 'width' => 20],
                ['key' => 'address',         'label' => $t('general', 'address', 'Address'), 'width' => 24],
                ['key' => 'country',         'label' => $t('ledger', 'country', 'Country'), 'width' => 14],
                ['key' => 'province',        'label' => $t('ledger', 'province', 'Province'), 'width' => 14],
                ['key' => 'group',           'label' => $t('ledger', 'customer_group', 'Customer Group'), 'width' => 16],
                ['key' => 'payment_term',    'label' => $t('ledger', 'payment_term', 'Payment Term'), 'width' => 16],
                ['key' => 'discount',        'label' => $t('general', 'discount', 'Discount'), 'type' => 'money', 'align' => 'right', 'width' => 12],
                ['key' => 'credit_limit',    'label' => $t('ledger', 'credit_limit', 'Credit Limit'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'balance',         'label' => $t('general', 'balance', 'Balance'), 'align' => 'right', 'width' => 18],
                ['key' => 'is_active',       'label' => $t('general', 'status', 'Status'), 'width' => 10],
            ],
            'rows' => $rows,
        ]);
    }

    protected function nextCode(?string $branchId): string
    {
        $latestNumber = Ledger::query()
            ->where('type', 'supplier')
            ->where('branch_id', $branchId)
            ->whereRaw('code ~ ?', ['^(SUP-)?[0-9]+$'])
            ->selectRaw("MAX(CAST(REGEXP_REPLACE(code, '^SUP-', '') AS INTEGER)) as max_code")
            ->value('max_code');

        $number = ((int) $latestNumber) + 1;

        return 'SUP-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}

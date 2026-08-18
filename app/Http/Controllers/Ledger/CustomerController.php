<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BuildsLedgerStatement;
use App\Http\Requests\Ledger\LedgerStoreRequest;
use App\Http\Requests\Ledger\LedgerUpdateRequest;
use App\Http\Resources\Ledger\LedgerResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Administration\BranchResource;
use App\Http\Resources\Sale\SaleResource;
use App\Http\Resources\Receipt\ReceiptResource;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Models\Administration\Currency;
use App\Models\Administration\Branch;
use App\Models\Administration\Country;
use App\Models\Administration\CustomerGroup;
use App\Models\Administration\PaymentTerm;
use App\Models\Administration\Province;
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
use App\Models\Transaction\TransactionLine;
use Illuminate\Support\Facades\DB;
use App\Support\Inertia\CacheKey;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Services\SpreadsheetExportService;
use App\Services\PdfExportService;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
class CustomerController extends Controller
{
    use BuildsLedgerStatement;

    public function __construct()
    {
        $this->authorizeResource(Ledger::class, 'customer');
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

        $type = $request->input('type', 'customer'); // default to customer

        $customers = Ledger::search($request->query('search'))
            ->where('type', $type) // Filter by type
            ->filter($filters)
            ->with(['currency', 'branch', 'group', 'country', 'province'])
            // Feeds the `statement` accessor so the list doesn't run one aggregate
            // query per row.
            ->withStatementTotals()
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Ledgers/Customers/Index', [
            'customers' => LedgerResource::collection($customers),
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
        return inertia('Ledgers/Customers/Create', [
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
        $validated['type'] = 'customer';
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
            return to_route('customers.create')
                ->with('success', __('general.created_successfully', ['resource' => __('general.resource.customer')]));
        }

        return to_route('customers.index')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.customer')]));

    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Ledger $customer, LedgerStatementService $statementService)
    {
        // Only the relations the Show page actually renders — the customer's whole
        // transaction-line history was being eager-loaded before, which made pages
        // for high-volume customers extremely slow.
        $customer->load([
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

        $lists = $this->transactionLists($customer);

        $ledgerStatement = $statementService->build($customer, $this->statementFilters($request));

        // Deliberately unfiltered: the profile card reports the party's standing
        // position, not whatever window the statement tab is currently showing.
        $currencyBalances = $statementService->balancesByCurrency($customer);

        // Open items are claims, not documents: an invoice, an opening balance
        // and a manual journal debit to receivables all appear here on equal
        // footing, each with the rate it was booked at.
        $settlements = app(PaymentStatusService::class);
        $openItems = app(SettlementService::class)->openItems($customer->id);
        $settlementHistory = $settlements->settlementHistoryForLedger($customer->id);
        $settlementBalances = $settlements->balancesForLedger($customer->id);

        if ($request->expectsJson()) {
            return response()->json([
                'customer' => new LedgerResource($customer),
                ...$lists,
                'ledgerStatement' => $ledgerStatement,
                'currencyBalances' => $currencyBalances,
                'openItems' => $openItems,
                'settlementHistory' => $settlementHistory,
                'settlementBalances' => $settlementBalances,
            ]);
        }

        return inertia('Ledgers/Customers/Show', [
            'customer' => new LedgerResource($customer),
            ...$lists,
            'ledgerStatement' => $ledgerStatement,
            'currencyBalances' => $currencyBalances,
            'openItems' => $openItems,
            'settlementHistory' => $settlementHistory,
            'settlementBalances' => $settlementBalances,
        ]);
    }

    /**
     * Lightweight sale/receipt/payment rows for the ledger Show page. Building full
     * resources here triggered heavy per-row work (profit/cost, warehouse lookups,
     * N+1 on the customer relation) that the summary tables never display.
     *
     * @return array{sales: array, receipts: array, payments: array}
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

        $sales = $ledger->sales()
            ->with(['items:id,sale_id,quantity,unit_price,discount,tax'])
            ->orderByDesc('date')->orderByDesc('id')->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'number' => $s->number,
                'date' => $dateService->toDisplay($s->date),
                'type' => $typeLabel($s->type),
                'amount' => $s->saleTotal(),
                'payment_status' => $s->payment_status?->value,
                'payment_status_label' => $s->payment_status?->getLabel(),
                'description' => $s->description,
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

        return compact('sales', 'receipts', 'payments');
    }

    /**
     * Lookup lists shared by the customer create and edit forms.
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
        Ledger $customer,
        SpreadsheetExportService $spreadsheetExportService,
        PdfExportService $pdfExportService,
    ): SymfonyResponse {
        $this->authorize('view', $customer);

        $validated = $request->validate([
            'list' => ['nullable', 'string', Rule::in(['sales', 'receipts', 'payments', 'statement'])],
            'format' => ['nullable', 'string', Rule::in(['xlsx', 'pdf'])],
        ]);

        $list = $validated['list'] ?? 'sales';
        $format = $validated['format'] ?? 'xlsx';
        $customer->loadMissing(['currency', 'branch']);

        $translate = fn (string $group, string $key, string $fallback = '') => $spreadsheetExportService->localeTranslation($group, $key, $fallback);

        $rows = match ($list) {
            'receipts' => $this->exportReceiptRows($customer),
            'payments' => $this->exportPaymentRows($customer),
            'statement' => $this->statementExportRows(
                app(LedgerStatementService::class)->build($customer, $this->statementFilters($request)),
                $translate,
            ),
            default => $this->exportSaleRows($customer),
        };

        $moduleLabel = match ($list) {
            'receipts' => $spreadsheetExportService->localeTranslation('receipt', 'receipts', 'Receipts'),
            'payments' => $spreadsheetExportService->localeTranslation('payment', 'payments', 'Payments'),
            'statement' => $spreadsheetExportService->localeTranslation('report', 'reports.customer_statement.label', 'Customer Statement'),
            default => $spreadsheetExportService->localeTranslation('sale', 'sales', 'Sales'),
        };

        // Name the party in the heading rather than repeating the generic
        // "Customer" label, which the module label already carries.
        $sheetTitle = $moduleLabel . ' ' . $customer->name;

        $payload = [
            'filename' => Str::slug($customer->name . '-' . $sheetTitle) . '-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name' => $sheetTitle,
            'sheet_title' => $sheetTitle,
            'title' => $customer->name . ' - ' . $moduleLabel,
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
    public function edit(Ledger $customer)
    {
        $customer->load([
            'currency', 'group', 'paymentTerm', 'country', 'province',
            'openings', 'openings.transaction.currency', 'openings.transaction.lines',
            'attachments',
        ]);

        return inertia('Ledgers/Customers/Edit', [
            'customer' => new LedgerResource($customer),
            ...$this->referenceData(),
        ]);
    }

    protected function exportSaleRows(Ledger $customer): array
    {
        $sales = $customer->sales()->with(['transaction.currency', 'items'])->orderBy('date')->orderBy('id')->get();
        $rows = collect(SaleResource::collection($sales)->resolve());

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

    protected function exportReceiptRows(Ledger $customer): array
    {
        $receipts = $customer->receipts()->with(['transaction.currency', 'transaction.lines.account'])->orderBy('date')->orderBy('id')->get();
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

    protected function exportPaymentRows(Ledger $customer): array
    {
        $payments = $customer->payments()->with(['transaction.currency', 'transaction.lines.account'])->orderBy('date')->orderBy('id')->get();
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
    public function updatePhoto(Request $request, Ledger $customer)
    {
        $this->authorize('update', $customer);
        abort_unless($customer->type?->value === 'customer', 404);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($customer->photo) {
            Storage::disk('public')->delete($customer->photo);
        }

        $customer->update([
            'photo' => $request->file('photo')->store('ledgers/photos', 'public'),
        ]);

        return back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        LedgerUpdateRequest $request,
        Ledger $customer,
        LedgerOpeningService $ledgerOpeningService,
        AttachmentService $attachmentService,
    ) {
        $validated = $request->validated();
        $validated['is_active'] = $validated['is_active'] ?? true;

        // A form without a new upload posts no `photo` key at all, so the existing
        // one must not be cleared.
        $attributes = Arr::except($validated, ['attachments', 'photo']);

        if ($request->hasFile('photo')) {
            if ($customer->photo) {
                Storage::disk('public')->delete($customer->photo);
            }

            $attributes['photo'] = $request->file('photo')->store('ledgers/photos', 'public');
        }

        $customer->update($attributes);

        if ($request->hasFile('attachments')) {
            $attachmentService->store($customer, $request->file('attachments'));
        }

        // Openings are rebuilt wholesale rather than diffed: the form always posts
        // the party's complete set, one row per currency.
        $ledgerOpeningService->sync($customer, $validated['openings'] ?? []);

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return to_route('customers.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.customer')]));
    }

    /**
     * Remove the specified resource from storage.
     */


    public function destroy(Request $request, Ledger $customer)
    {
        $openingTransactionIds = $customer->openings()->pluck('transaction_id')->filter()->all();

        // Allow delete only when customer has no transactions OR only opening transactions.
        $hasNonOpeningTransactions = TransactionLine::query()
            ->where('ledger_id', $customer->id)
            ->when(
                $openingTransactionIds !== [],
                fn ($q) => $q->whereNotIn('transaction_id', $openingTransactionIds),
                fn ($q) => $q // no opening found -> any transaction means blocked
            )
            ->exists();

        if ($hasNonOpeningTransactions) {
            return back()->with('error', __('Cannot delete customer: this customer has transactions. Please remove related transactions first.'));
        }

        DB::transaction(function () use ($customer, $openingTransactionIds) {
            if ($openingTransactionIds !== []) {
                // Delete the whole opening transaction (both lines) and the opening record.
                TransactionLine::whereIn('transaction_id', $openingTransactionIds)->delete();
                Transaction::whereIn('id', $openingTransactionIds)->delete();
                $customer->openings()->delete();
            }

            $customer->delete();
        });

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()
            ->route('customers.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.customer')]));
    }

    public function restore(Request $request, Ledger $customer)
    {
        $openingTransactionIds = $customer->openings()->withTrashed()->pluck('transaction_id')->filter()->all();

        DB::transaction(function () use ($customer, $openingTransactionIds) {
            if ($openingTransactionIds !== []) {
                Transaction::withTrashed()->whereIn('id', $openingTransactionIds)->restore();
                TransactionLine::withTrashed()->whereIn('transaction_id', $openingTransactionIds)->restore();
                $customer->openings()->withTrashed()->restore();
            }

            $customer->restore();
        });

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('customers.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.customer')]));
    }

    public function forceDelete(Request $request, Ledger $customer)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('customers', (string) $customer->id);

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('customers.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.customer')]));
    }

    public function exportList(Request $request, SpreadsheetExportService $spreadsheetExportService)
    {
        $this->authorize('viewAny', Ledger::class);

        $sortField = $request->input('sortField', 'name');
        $sortDirection = $request->input('sortDirection', 'asc');
        $filters = (array) $request->input('filters', []);

        $customers = Ledger::search($request->query('search'))
            ->where('type', 'customer')
            ->filter($filters)
            ->with(['group', 'paymentTerm', 'country', 'province'])
            // Feeds the statement accessor, so the balance column costs one
            // query for the whole sheet instead of one per row.
            ->withStatementTotals()
            ->orderBy($sortField, $sortDirection)
            ->get();

        $rtl = in_array(app()->getLocale(), ['fa', 'ps'], true);
        $t = fn (string $group, string $key, string $fallback = '') => $spreadsheetExportService->localeTranslation($group, $key, $fallback);

        $rows = $customers->map(fn ($c) => [
            'name'            => $c->name ?? '-',
            'code'            => $c->code ?? '-',
            'contact_person'  => $c->contact_person ?? '-',
            'phone_no'        => $c->phone_no ?? '-',
            'whatsapp_number' => $c->whatsapp_number ?? '-',
            'email'           => $c->email ?? '-',
            'address'         => $c->address ?? '-',
            'country'         => $c->country?->localized_name ?? '-',
            'province'        => $c->province?->localized_name ?? '-',
            'group'           => $c->group?->localized_name ?? '-',
            'payment_term'    => $c->paymentTerm?->name ?? '-',
            'discount'        => $c->discount !== null ? (float) $c->discount : '-',
            'credit_limit'    => $c->credit_limit !== null ? (float) $c->credit_limit : '-',
            // The same preference-aware string the list and detail pages show,
            // so an exported balance reads the way the user set it to read.
            'balance'         => (string) $c->statement['balance'],
            'is_active'       => $c->is_active ? $t('general', 'active', 'Active') : $t('general', 'inactive', 'Inactive'),
        ])->all();

        $label = $t('ledger', 'customer.customers', 'Customers');

        return $spreadsheetExportService->download([
            'filename'           => 'customers-' . now()->format('Ymd-His') . '.xlsx',
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
            ->where('type', 'customer')
            ->where('branch_id', $branchId)
            ->whereRaw('code ~ ?', ['^(CUST-)?[0-9]+$'])
            ->selectRaw("MAX(CAST(REGEXP_REPLACE(code, '^CUST-', '') AS INTEGER)) as max_code")
            ->value('max_code');

        $number = ((int) $latestNumber) + 1;

        return 'CUST-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}

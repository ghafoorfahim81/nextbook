<?php

namespace App\Http\Controllers\Receipt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Receipt\ReceiptStoreRequest;
use App\Http\Requests\Receipt\ReceiptUpdateRequest;
use App\Http\Resources\Receipt\ReceiptResource;
use App\Enums\PaymentMode;
use App\Enums\TransactionStatus;
use App\Models\Account\Account;
use App\Models\Accounting\Settlement;
use App\Models\Ledger\Ledger;
use App\Models\Receipt\Receipt;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Services\Accounting\PaymentStatusService;
use App\Services\Accounting\SettlementService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Support\Inertia\CacheKey;
use App\Models\Administration\Currency;
use App\Models\User;
use App\Services\DateConversionService;
use App\Services\ActivityLogService;
use App\Services\AttachmentService;
use App\Support\BranchContext;
class ReceiptController extends Controller
{
    use \App\Http\Controllers\Concerns\ListsCashMovements;

    private $dateConversionService;
    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(Receipt::class, 'receipt');
        $this->dateConversionService = $dateConversionService;
    }

    /**
     * Re-derive the paid/partly-paid badge on whatever this voucher settled.
     *
     * Driven off the settlements rows rather than a list the form sent, so it
     * stays right when an allocation was split across rates or dropped.
     */
    private function refreshSettledDocuments(string $transactionId): void
    {
        $statuses = app(PaymentStatusService::class);
        $affected = $statuses->documentsSettledBy($transactionId);

        $statuses->recalculateSales($affected['sales']);
    }

    /**
     * The settlement voucher for a receipt.
     *
     * Built in one place because it is needed twice — once when a receipt posts
     * straight away, and again when a draft is posted later from its stored
     * payload — and the two must describe the same voucher.
     *
     * @return array<string, mixed>
     */
    private function settlementVoucher(
        Receipt $receipt,
        Ledger $ledger,
        array $validated,
        string $bankAccountId,
        string $currencyId,
        float $rate,
        float $amount
    ): array {
        return array_filter([
            'ledger_id' => $ledger->id,
            // Money coming IN. Stated by the module, never inferred from the
            // party — a receipt from a supplier is a real thing (they are
            // refunding an advance), and it still has to debit cash.
            'direction' => SettlementService::DIRECTION_IN,
            'date' => $validated['date'],
            'cash_account_id' => $bankAccountId,
            'cash_currency_id' => $currencyId,
            'cash_rate' => $rate,
            'cash_amount' => $amount,
            'applied_cash_amount' => $validated['applied_cash_amount'] ?? null,
            'applied_cash' => $validated['applied_cash'] ?? null,
            'voucher_number' => $validated['cheque_no'] ?? 'Receipt #' . $receipt->number,
            'reference_type' => Receipt::class,
            'reference_id' => $receipt->id,
            'remark' => $validated['narration'] ?? "Receipt #{$receipt->number} from {$ledger->name}",
            'remark_fa' => 'دریافت نقدی رسید #' . $receipt->number . ' از ' . $ledger->name,
            'remark_ps' => 'د' . '#' . $receipt->number . ' ' . 'د نغدي اخیستلو په اړه رسید له  ' . $ledger->name,
        ], fn ($value) => $value !== null);
    }

    /**
     * Post a drafted receipt: settle it now, using the voucher and the invoices
     * chosen when the draft was saved.
     *
     * The draft's placeholder transaction carried no lines, so it is discarded
     * and SettlementService posts the real one. Allocations are re-validated by
     * the service against what is still open, so an invoice settled by some
     * other voucher in the meantime is rejected rather than double-relieved.
     */
    public function post(Receipt $receipt)
    {
        $this->authorize('update', $receipt);

        if ($receipt->status !== TransactionStatus::DRAFT->value) {
            abort(422, 'Only draft documents can be posted.');
        }

        DB::transaction(function () use ($receipt) {
            $draft = $receipt->transaction()->firstOrFail();

            $voucher = (array) data_get($draft->posting_payload, 'settlement_voucher', []);
            $allocations = (array) data_get($draft->posting_payload, 'allocations', []);

            if ($voucher === []) {
                abort(422, 'This draft has no settlement voucher to post.');
            }

            $draft->delete();

            $transaction = app(SettlementService::class)->settle(
                voucher: $voucher,
                allocations: $allocations,
            );

            $this->refreshSettledDocuments($transaction->id);

            $receipt->update(['status' => TransactionStatus::POSTED->value]);
        });

        return back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.receipt')]));
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $query = Receipt::with(['ledger', 'transaction.currency', 'transaction.lines.account.accountType', 'settlements', 'createdBy', 'updatedBy'])
            ->search($request->query('search'))
            ->filter($filters);

        $receipts = $this->applyCashMovementSort($query, $sortField, $sortDirection, Receipt::class)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Receipts/Index', [
            'receipts' => ReceiptResource::collection($receipts),
            'filterOptions' => [
                'customers' => Ledger::query()->where('type', 'customer')->orderBy('name')->get(['id', 'name']),
                'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name']),
                'bankAccounts' => $this->cashBankAccountOptions(),
                'paymentModes' => $this->paymentModeOptions(),
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


    public function create(Request $request)
    {
        $latest = Receipt::nextNumber();
        return inertia('Receipts/Create', [
            'latestNumber' => $latest,
            // When opened from a customer/supplier page (?ledger_id=...), preselect that
            // ledger even though the ledger options are loaded on demand via search.
            'preselectedLedger' => $this->resolvePreselectedLedger($request->query('ledger_id')),
            'paymentModes' => collect(PaymentMode::cases())->map(fn (PaymentMode $mode) => [
                'id' => $mode->value,
                'name' => $mode->getLabel(),
            ])->values(),
        ]);
    }

    /**
     * The ledger a receipt form was opened for, resolved eagerly.
     *
     * The ledger dropdown loads its options by search, so a form arriving with
     * ?ledger_id= would otherwise render with an empty box — the option it
     * needs has not been fetched yet. Credit fields come along because the form
     * warns on over-limit parties.
     *
     * @return array<string, mixed>|null
     */
    private function resolvePreselectedLedger(?string $ledgerId): ?array
    {
        if (! $ledgerId) {
            return null;
        }

        $ledger = Ledger::query()
            ->select([
                'id',
                'name',
                'code',
                'type',
                'email',
                'phone_no',
                'address',
                'currency_id',
                'credit_limit',
                'credit_limit_enabled',
                'credit_terms',
                'is_active',
                'branch_id',
            ])
            ->withStatementTotals()
            ->find($ledgerId);

        return $ledger
            ? \App\Http\Resources\Ledger\LedgerOptionResource::make($ledger)->resolve()
            : null;
    }

    public function store(
        ReceiptStoreRequest $request,
        TransactionService $transactionService,
        ActivityLogService $activityLogService,
        AttachmentService $attachmentService
    )
    {
        $receipt = DB::transaction(function () use ($request, $transactionService, $activityLogService, $attachmentService) {
            $validated = $request->validated();

            $postImmediately = (bool) user_preference('transaction.receipt_post_immediately', true);

            $ledger = Ledger::findOrFail($validated['ledger_id']);
            $amount = (float) $validated['amount'];
            $currencyId = $validated['currency_id'];
            $rate = (float) $validated['rate'];
            $bankAccountId = $validated['bank_account_id'];
            $validated['date'] = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null;
            $paymentMode = $validated['payment_mode'] ?? PaymentMode::OnAccount->value;
            $bankAccount = Account::find($bankAccountId);
            $receipt = Receipt::create([
                'number' => $validated['number'],
                'date' => $validated['date'],
                'ledger_id' => $ledger->id,
                'payment_mode' => $paymentMode,
                'cheque_no' => $validated['cheque_no'] ?? null,
                'narration' => $validated['narration'] ?? null,
                'status' => $postImmediately
                    ? TransactionStatus::POSTED->value
                    : TransactionStatus::DRAFT->value,
            ]);

            if ($request->hasFile('attachments')) {
                $attachmentService->store($receipt, $request->file('attachments'));
            }

            $voucher = $this->settlementVoucher($receipt, $ledger, $validated, $bankAccountId, $currencyId, $rate, $amount);

            // SettlementService posts the whole voucher: cash at today's rate,
            // one receivable line per booking rate it relieves, and the FX
            // difference between them. It is deliberately not this controller's
            // job to decide any of that.
            //
            // A draft settles nothing yet: it parks the voucher and the chosen
            // invoices on a lines-less draft transaction and relieves them only
            // when it is posted, because claiming an open invoice before the
            // money is real would close one nobody has paid.
            $transaction = $postImmediately
                ? app(SettlementService::class)->settle(
                    voucher: $voucher,
                    allocations: $validated['allocations'] ?? [],
                )
                : $transactionService->post(
                    header: [
                        'currency_id' => $currencyId,
                        'rate' => $rate,
                        'date' => $validated['date'],
                        'voucher_number' => $voucher['voucher_number'],
                        'reference_type' => Receipt::class,
                        'reference_id' => $receipt->id,
                        'remark' => $voucher['remark'],
                        'status' => TransactionStatus::DRAFT->value,
                        'posting_payload' => [
                            'settlement_voucher' => $voucher,
                            'allocations' => $validated['allocations'] ?? [],
                            'amount' => $amount,
                        ],
                    ],
                    lines: [],
                );

            if ($postImmediately) {
                $this->refreshSettledDocuments($transaction->id);
            }

            $activityLogService->logCreate(
                reference: $receipt,
                module: 'receipt',
                description: "Receipt #{$receipt->number} created.",
                newValues: [
                    'number' => $receipt->number,
                    'date' => $receipt->date?->toDateString(),
                    'voucher_number' => $validated['cheque_no'] ?? 'Receipt #' . $receipt->number,
                    'customer_name' => $ledger->name,
                    'payment_method' => $bankAccount?->name,
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                ],
                metadata: [
                    'action' => 'receipt_store',
                    'transaction_id' => $transaction->id,
                ],
            );

            return $receipt;
        });
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        if ($request->input('create_and_new')) {
            return redirect()->route('receipts.create')->with('success', __('general.created_successfully', ['resource' => __('general.resource.receipt')]));
        }

        $redirect = redirect()->route('receipts.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.receipt')]));

        if ($request->boolean('create_and_print')) {
            $redirect->with('print_url', route('receipts.print', $receipt));
        }

        return $redirect;
    }

    public function show(Request $request, Receipt $receipt)
    {
        $receipt->load([
            'ledger',
            'transaction.currency',
            'transaction.lines.account.accountType',
            'transaction.lines.currency',
            'settlements',
            'createdBy',
            'updatedBy',
            'attachments',
        ]);

        $resource = new ReceiptResource($receipt);

        // The edit form fetches this endpoint over axios to populate itself, so
        // the JSON shape has to stay. Browsers get the page.
        if ($request->expectsJson()) {
            return response()->json(['data' => $resource]);
        }

        return inertia('Receipts/Show', [
            'receipt' => $resource,
            // Named documents rather than raw line ids — the settlement rows
            // alone cannot say which invoice or opening they relieved.
            'settlements' => $receipt->transaction
                ? app(SettlementService::class)->settlementsForVoucher($receipt->transaction->id)
                : [],
        ]);
    }

    public function print(Request $request, Receipt $receipt, ActivityLogService $activityLogService)
    {
        $this->authorize('view', $receipt);

        $receipt->load([
            'ledger',
            'transaction.currency',
            'transaction.lines.account.accountType',
            'transaction.lines.ledger',
            'settlements',
            'createdBy',
            'updatedBy',
            'attachments',
        ]);

        $activityLogService->logAction(
            eventType: 'print',
            reference: $receipt,
            module: 'receipt',
            description: "Receipt #{$receipt->number} printed.",
            metadata: [
                'action' => 'receipt_print',
            ],
        );

        return inertia('Vouchers/Print', [
            'voucher' => new ReceiptResource($receipt),
            'company' => auth()->user()?->company,
            'voucherType' => 'receipt',
            'ledgerBalance' => $receipt->ledger?->statement,
        ]);
    }


    public function edit(Request $request, Receipt $receipt)
    {
        $receipt->load(['ledger', 'transaction.currency', 'transaction.lines.account.accountType', 'settlements', 'createdBy', 'updatedBy']);
        return inertia('Receipts/Edit', [
            'data' => new ReceiptResource($receipt),
            'paymentModes' => collect(PaymentMode::cases())->map(fn (PaymentMode $mode) => [
                'id' => $mode->value,
                'name' => $mode->getLabel(),
            ])->values(),
        ]);
    }

    public function update(
        ReceiptUpdateRequest $request,
        Receipt $receipt,
        ActivityLogService $activityLogService,
        AttachmentService $attachmentService
    ) {
        $beforeState = [
            'number' => $receipt->number,
            'date' => $receipt->date?->toDateString(),
            'ledger_id' => $receipt->ledger_id,
            'amount' => (float) ($receipt->transaction?->lines()->max('credit') ?? 0),
            'currency_id' => $receipt->transaction?->currency_id,
            'rate' => $receipt->transaction?->rate,
        ];

        DB::transaction(function () use ($request, $receipt, $activityLogService, $beforeState, $attachmentService) {
            $validated = $request->validated();

            if ($request->hasFile('attachments')) {
                $attachmentService->store($receipt, $request->file('attachments'));
            }

            $validated['date'] = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $receipt->date;
            $currentPaymentMode = $receipt->payment_mode instanceof PaymentMode
                ? $receipt->payment_mode->value
                : $receipt->payment_mode;
            $paymentMode = $validated['payment_mode'] ?? $currentPaymentMode ?? PaymentMode::OnAccount->value;
            $receipt->update([
                'number' => $validated['number'],
                'date' => $validated['date'],
                'ledger_id' => $validated['ledger_id'],
                'payment_mode' => $paymentMode,
                'cheque_no' => $validated['cheque_no'] ?? null,
                'narration' => $validated['narration'] ?? null,
            ]);

            // Keep accounts aligned and update both transactions
            $ledger = Ledger::findOrFail($receipt->ledger_id);
            $amount = isset($validated['amount']) ? (float) $validated['amount'] : $receipt->amount;
            $currencyId = $validated['currency_id'] ?? $receipt->currency_id;
            $rate = isset($validated['rate']) ? (float) $validated['rate'] : $receipt->rate;
            $bankAccountId = $validated['bank_account_id'] ?? $receipt->transaction?->lines[0]->account_id;
            $bankAccount = Account::find($bankAccountId);

            // Editing a receipt re-posts it from scratch. The settlements it
            // wrote go with the old voucher — leaving them behind would keep
            // invoices closed against a journal entry that no longer exists.
            $oldTransaction = $receipt->transaction()->first();

            if ($oldTransaction) {
                $affected = app(PaymentStatusService::class)->documentsSettledBy($oldTransaction->id);

                Settlement::withoutGlobalScopes()->where('transaction_id', $oldTransaction->id)->forceDelete();
                TransactionLine::where('transaction_id', $oldTransaction->id)->forceDelete();
                Transaction::where('id', $oldTransaction->id)->forceDelete();

                app(PaymentStatusService::class)->recalculateSales($affected['sales']);
            }

            $transaction = app(SettlementService::class)->settle(
                voucher: array_filter([
                    'ledger_id' => $ledger->id,
                    // Money coming IN. Stated by the module, never inferred
                    // from the party — a receipt from a supplier is a real
                    // thing (they are refunding an advance), and it still has
                    // to debit cash.
                    'direction' => SettlementService::DIRECTION_IN,
                    'date' => $validated['date'],
                    'cash_account_id' => $bankAccountId,
                    'cash_currency_id' => $currencyId,
                    'cash_rate' => $rate,
                    'cash_amount' => $amount,
                    'applied_cash_amount' => $validated['applied_cash_amount'] ?? null,
                    'applied_cash' => $validated['applied_cash'] ?? null,
                    'voucher_number' => $validated['cheque_no'] ?? 'Receipt #' . $receipt->number,
                    'reference_type' => Receipt::class,
                    'reference_id' => $receipt->id,
                    'remark' => $validated['narration'] ?? "Receipt #{$receipt->number} from {$ledger->name}",
                    'remark_fa' => 'دریافت نقدی رسید #' . $receipt->number . ' از ' . $ledger->name,
                    'remark_ps' => 'د' . '#' . $receipt->number . ' ' . 'د نغدي اخیستلو په اړه رسید له  ' . $ledger->name,
                ], fn ($value) => $value !== null),
                allocations: $validated['allocations'] ?? [],
            );

            $this->refreshSettledDocuments($transaction->id);
            Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

            $activityLogService->logUpdate(
                reference: $receipt,
                before: $beforeState,
                after: [
                    'number' => $receipt->number,
                    'date' => $receipt->date?->toDateString(),
                    'customer_name' => $ledger->name,
                    'payment_method' => $bankAccount?->name,
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                ],
                module: 'receipt',
                description: "Receipt #{$receipt->number} updated.",
                metadata: [
                    'action' => 'receipt_update',
                    'transaction_id' => $transaction->id,
                ],
            );
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        });

        $redirect = redirect()->route('receipts.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.receipt')]));

        if ($request->boolean('save_and_print')) {
            $redirect->with('print_url', route('receipts.print', $receipt));
        }

        return $redirect;
    }

    public function destroy(Request $request, Receipt $receipt, ActivityLogService $activityLogService)
    {
        $oldValues = [
            'number' => $receipt->number,
            'date' => $receipt->date?->toDateString(),
            'customer_name' => $receipt->ledger?->name,
            'payment_method' => $receipt->transaction?->lines?->first()?->account?->name,
            'amount' => (float) ($receipt->transaction?->lines()->max('credit') ?? 0),
            'currency_id' => $receipt->transaction?->currency_id,
            'rate' => $receipt->transaction?->rate,
        ];

        DB::transaction(function () use ($receipt) {
            $transaction = $receipt->transaction()->first();
            $affected = ['sales' => []];

            if ($transaction) {
                // Note which invoices this receipt was holding closed BEFORE
                // the settlements go, so their badges can be re-derived after.
                $affected = app(PaymentStatusService::class)->documentsSettledBy($transaction->id);

                Settlement::withoutGlobalScopes()->where('transaction_id', $transaction->id)->delete();
                $transaction->lines()->delete();
                $transaction->delete();
            }

            $receipt->delete();

            app(PaymentStatusService::class)->recalculateSales($affected['sales']);
        });

        $activityLogService->logDelete(
            reference: $receipt,
            module: 'receipt',
            description: "Receipt #{$receipt->number} deleted.",
            oldValues: $oldValues,
            metadata: [
                'action' => 'receipt_delete',
            ],
        );
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('receipts.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.receipt')]));
    }
    public function restore(Request $request, Receipt $receipt, ActivityLogService $activityLogService)
    {
        DB::transaction(function () use ($receipt) {
            $transaction = $receipt->transaction()->withTrashed()->first();

            if ($transaction) {
                $transaction->restore();
                $transaction->lines()->withTrashed()->restore();
                Settlement::withoutGlobalScopes()
                    ->onlyTrashed()
                    ->where('transaction_id', $transaction->id)
                    ->restore();
            }

            $receipt->restore();

            if ($transaction) {
                $this->refreshSettledDocuments($transaction->id);
            }
        });

        $activityLogService->logAction(
            eventType: 'restored',
            reference: $receipt,
            module: 'receipt',
            description: "Receipt #{$receipt->number} restored.",
            newValues: [
                'number' => $receipt->number,
                'customer_name' => $receipt->ledger?->name,
                'payment_method' => $receipt->transaction?->lines?->first()?->account?->name,
            ],
            metadata: [
                'action' => 'receipt_restore',
            ],
        );
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('receipts.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.receipt')]));
    }

    public function forceDelete(Request $request, Receipt $receipt)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('receipts', (string) $receipt->id);

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('receipts.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.receipt')]));
    }

    public function export(Request $request, \App\Services\SpreadsheetExportService $exporter)
    {
        $this->authorize('viewAny', Receipt::class);

        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $exportQuery = Receipt::with(['ledger', 'transaction.currency', 'transaction.lines.account.accountType'])
            ->search($request->query('search'))
            ->filter($filters);

        $receipts = $this->applyCashMovementSort($exportQuery, $sortField, $sortDirection, Receipt::class)->get();

        $rtl = in_array(app()->getLocale(), ['fa', 'ps'], true);
        $company = $request->user()?->company;
        $companyName = match (app()->getLocale()) {
            'fa'    => $company?->name_fa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            'ps'    => $company?->name_pa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            default => $company?->name_en ?: $company?->abbreviation ?: $company?->name_fa ?: $company?->name_pa ?: config('app.name'),
        };
        $t = fn (string $group, string $key, string $fallback = '') => $exporter->localeTranslation($group, $key, $fallback);
        // Accounts are named twice — once in English, once in the local
        // language — and the sheet should read in whichever the user is in.
        $locale = app()->getLocale();
        $accountName = fn (?\App\Models\Account\Account $account) => $account
            ? ($locale === 'en' ? $account->name : ($account->local_name ?: $account->name))
            : '-';

        $rows = $receipts->map(fn ($r) => [
            'number'       => $r->number,
            'ledger_name'  => $r->ledger?->name ?? '-',
            // payment_mode is cast to the PaymentMode enum on the model, so
            // (string) $r->payment_mode is a fatal error, not a value.
            'payment_mode' => PaymentMode::labelFor($r->payment_mode),
            // The cash line, not lines[0]: a settlement voucher carries the
            // ledger relief and any exchange difference alongside the cash,
            // in an order Postgres is free to choose.
            'bank_account' => $accountName($r->bankAccount()),
            'amount'       => $r->receivedAmount(),
            'currency'     => $r->transaction?->currency?->code ?? '-',
            'rate'         => $r->transaction?->rate !== null ? (float) $r->transaction->rate : '-',
            'cheque_no'    => $r->cheque_no ?: '-',
            'date'         => $r->date ? $this->dateConversionService->toDisplay($r->date) : '-',
            'narration'    => $r->narration ?: '-',
        ])->all();

        $label = $t('receipt', 'receipts', 'Receipts');

        return $exporter->download([
            'filename'           => 'receipts-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name'         => $label,
            'sheet_title'        => $label,
            'title'              => $label,
            'company_name'       => $companyName,
            'exported_on'        => now()->format('Y m d'),
            'rtl'                => $rtl,
            'include_row_number' => true,
            'row_number_label'   => $t('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'number',       'label' => $t('general', 'number', 'Number'), 'width' => 10],
                ['key' => 'ledger_name',  'label' => $t('general', 'ledger', 'Ledger'), 'width' => 20],
                ['key' => 'payment_mode', 'label' => $t('general', 'payment_mode', 'Payment Mode'), 'width' => 16],
                ['key' => 'bank_account', 'label' => $t('expense', 'bank_account', 'Bank/Cash Account'), 'width' => 22],
                ['key' => 'amount',       'label' => $t('general', 'amount', 'Amount'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'currency',     'label' => $t('admin', 'currency.currency', 'Currency'), 'width' => 10],
                ['key' => 'rate',         'label' => $t('general', 'rate', 'Rate'), 'type' => 'money', 'align' => 'right', 'width' => 10],
                ['key' => 'cheque_no',    'label' => $t('general', 'cheque_no', 'Cheque No'), 'width' => 14],
                ['key' => 'date',         'label' => $t('general', 'date', 'Date'), 'width' => 14],
                ['key' => 'narration',    'label' => $t('general', 'narration', 'Narration'), 'width' => 30],
            ],
            'rows' => $rows,
        ]);
    }
}

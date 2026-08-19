<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\PaymentStoreRequest;
use App\Http\Requests\Payment\PaymentUpdateRequest;
use App\Http\Resources\Payment\PaymentResource;
use App\Enums\PaymentMode;
use App\Enums\TransactionStatus;
use App\Models\Account\Account;
use App\Models\Accounting\Settlement;
use App\Models\Ledger\Ledger;
use App\Models\Payment\Payment;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Services\Accounting\PaymentStatusService;
use App\Services\Accounting\SettlementService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Support\Inertia\CacheKey;
use App\Models\Administration\Currency;
use App\Models\User;
use App\Services\DateConversionService;
use App\Services\ActivityLogService;
use App\Services\AttachmentService;
class PaymentController extends Controller
{
    use \App\Http\Controllers\Concerns\ListsCashMovements;

    private $dateConversionService;
    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(Payment::class, 'payment');
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

        $statuses->recalculatePurchases($affected['purchases']);
    }

    /**
     * The settlement voucher for a payment.
     *
     * Built in one place because it is needed twice — once when a payment posts
     * straight away, and again when a draft is posted later from its stored
     * payload — and the two must describe the same voucher.
     *
     * @return array<string, mixed>
     */
    private function settlementVoucher(
        Payment $payment,
        Ledger $ledger,
        array $validated,
        string $bankAccountId,
        string $currencyId,
        float $rate,
        float $amount
    ): array {
        return array_filter([
            'ledger_id' => $ledger->id,
            // Money going OUT. Stated by the module, never inferred from the
            // party — paying a customer (refunding them) is a real thing, and
            // it must still credit cash.
            'direction' => SettlementService::DIRECTION_OUT,
            'date' => $validated['date'],
            'cash_account_id' => $bankAccountId,
            'cash_currency_id' => $currencyId,
            'cash_rate' => $rate,
            'cash_amount' => $amount,
            'applied_cash_amount' => $validated['applied_cash_amount'] ?? null,
            'applied_cash' => $validated['applied_cash'] ?? null,
            'voucher_number' => $validated['cheque_no'] ?? 'Payment #' . $payment->number,
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'remark' => $validated['narration'] ?? "Payment #{$payment->number} to {$ledger->name}",
            'remark_fa' => 'پرداخت نقدی #' . $payment->number . ' به ' . $ledger->name,
            'remark_ps' => $ledger->name . ' ته د #' . $payment->number . ' ورکړه',
        ], fn ($value) => $value !== null);
    }

    /**
     * Post a drafted payment: settle it now, using the voucher and the bills
     * chosen when the draft was saved.
     *
     * The draft's placeholder transaction carried no lines, so it is discarded
     * and SettlementService posts the real one. Allocations are re-validated by
     * the service against what is still open, so a bill that was paid by some
     * other voucher in the meantime is rejected rather than double-relieved.
     */
    public function post(Payment $payment)
    {
        $this->authorize('update', $payment);

        if ($payment->status !== TransactionStatus::DRAFT->value) {
            abort(422, 'Only draft documents can be posted.');
        }

        DB::transaction(function () use ($payment) {
            $draft = $payment->transaction()->firstOrFail();

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

            $payment->update(['status' => TransactionStatus::POSTED->value]);
        });

        return back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.payment')]));
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $query = Payment::with(['ledger', 'transaction.currency', 'transaction.lines.account.accountType', 'settlements', 'createdBy', 'updatedBy'])
            ->search($request->query('search'))
            ->filter($filters);

        $payments = $this->applyCashMovementSort($query, $sortField, $sortDirection, Payment::class)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Payments/Index', [
            'payments' => PaymentResource::collection($payments),
            'filterOptions' => [
                'suppliers' => Ledger::query()->where('type', 'supplier')->orderBy('name')->get(['id', 'name']),
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
        $latest = Payment::nextNumber();
        return inertia('Payments/Create', [
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
     * The ledger a payment form was opened for, resolved eagerly.
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

    public function latestNumber(Request $request)
    {
        return response()->json([
            'number' => Payment::nextNumber(),
        ]);
    }

    public function store(
        PaymentStoreRequest $request,
        TransactionService $transactionService,
        ActivityLogService $activityLogService,
        AttachmentService $attachmentService
    )
    {
        $payment = DB::transaction(function () use ($request, $transactionService, $activityLogService, $attachmentService) {
            $validated = $request->validated();

            $postImmediately = (bool) user_preference('transaction.payment_post_immediately', true);

            $ledger = Ledger::findOrFail($validated['ledger_id']);
            $amount = (float) $validated['amount'];
            $currencyId = $validated['currency_id'];
            $rate = (float) $validated['rate'];
            $validated['date'] = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null;
            $bankAccountId = $validated['bank_account_id'];
            $paymentMode = $validated['payment_mode'] ?? PaymentMode::OnAccount->value;
            $bankAccount = Account::find($bankAccountId);
            $payment = Payment::create([
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
                $attachmentService->store($payment, $request->file('attachments'));
            }

            $voucher = $this->settlementVoucher($payment, $ledger, $validated, $bankAccountId, $currencyId, $rate, $amount);

            // The purchase side is the exact mirror of the sale side: same
            // service, same settlements table, payable account instead of
            // receivable. SettlementService relieves each bill at the rate it
            // was booked at and posts the exchange difference.
            //
            // A draft settles nothing yet: it parks the voucher and the chosen
            // bills on a lines-less draft transaction and relieves them only
            // when it is posted, because claiming an open bill before the money
            // is real would close an invoice nobody has paid.
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
                        'reference_type' => Payment::class,
                        'reference_id' => $payment->id,
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
                reference: $payment,
                module: 'payment',
                description: "Payment #{$payment->number} created.",
                newValues: [
                    'number' => $payment->number,
                    'date' => $payment->date?->toDateString(),
                    'supplier_name' => $ledger->name,
                    'payment_method' => $bankAccount?->name,
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                ],
                metadata: [
                    'action' => 'payment_store',
                    'transaction_id' => $transaction->id,
                ],
            );

            return $payment;

        });

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        if ($request->input('create_and_new')) {
            return redirect()->route('payments.create')->with('success', __('general.created_successfully', ['resource' => __('general.resource.payment')]));
        }

        $redirect = redirect()->route('payments.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.payment')]));

        if ($request->boolean('create_and_print')) {
            $redirect->with('print_url', route('payments.print', $payment));
        }

        return $redirect;
    }

    public function show(Request $request, Payment $payment)
    {
        $payment->load([
            'ledger',
            'transaction.currency',
            'transaction.lines.account.accountType',
            'transaction.lines.currency',
            'settlements',
            'createdBy',
            'updatedBy',
            'attachments',
        ]);

        $resource = new PaymentResource($payment);

        // The edit form fetches this endpoint over axios to populate itself, so
        // the JSON shape has to stay. Browsers get the page.
        if ($request->expectsJson()) {
            return response()->json(['data' => $resource]);
        }

        return inertia('Payments/Show', [
            'payment' => $resource,
            'settlements' => $payment->transaction
                ? app(SettlementService::class)->settlementsForVoucher($payment->transaction->id)
                : [],
        ]);
    }

    public function print(Request $request, Payment $payment, ActivityLogService $activityLogService)
    {
        $this->authorize('view', $payment);

        $payment->load([
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
            reference: $payment,
            module: 'payment',
            description: "Payment #{$payment->number} printed.",
            metadata: [
                'action' => 'payment_print',
            ],
        );

        return inertia('Vouchers/Print', [
            'voucher' => new PaymentResource($payment),
            'company' => auth()->user()?->company,
            'voucherType' => 'payment',
            'ledgerBalance' => $payment->ledger?->statement,
        ]);
    }

    public function edit(Request $request, Payment $payment)
    {
        $payment->load(['ledger', 'transaction.currency', 'transaction.lines.account.accountType', 'settlements', 'createdBy', 'updatedBy']);
        return inertia('Payments/Edit', [
            'data' => new PaymentResource($payment),
            'paymentModes' => collect(PaymentMode::cases())->map(fn (PaymentMode $mode) => [
                'id' => $mode->value,
                'name' => $mode->getLabel(),
            ])->values(),
        ]);
    }

    public function update(
        PaymentUpdateRequest $request,
        Payment $payment,
        ActivityLogService $activityLogService,
        AttachmentService $attachmentService
    ) {
        $beforeState = [
            'number' => $payment->number,
            'date' => $payment->date?->toDateString(),
            'ledger_id' => $payment->ledger_id,
            'amount' => (float) ($payment->transaction?->lines()->max('debit') ?? 0),
            'currency_id' => $payment->transaction?->currency_id,
            'rate' => $payment->transaction?->rate,
        ];

        DB::transaction(function () use ($request, $payment, $activityLogService, $beforeState, $attachmentService) {
            $validated = $request->validated();

            if ($request->hasFile('attachments')) {
                $attachmentService->store($payment, $request->file('attachments'));
            }

            $validated['date'] = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $payment->date;
            $currentPaymentMode = $payment->payment_mode instanceof PaymentMode
                ? $payment->payment_mode->value
                : $payment->payment_mode;
            $paymentMode = $validated['payment_mode'] ?? $currentPaymentMode ?? PaymentMode::OnAccount->value;
            $payment->update([
                'number' => $validated['number'] ?? $payment->number,
                'date' => $validated['date'],
                'ledger_id' => $validated['ledger_id'] ?? $payment->ledger_id,
                'payment_mode' => $paymentMode,
                'cheque_no' => $validated['cheque_no'] ?? $payment->cheque_no,
                'narration' => $validated['narration'] ?? $payment->narration,
            ]);

            $ledger = Ledger::findOrFail($payment->ledger_id);
            $amount = isset($validated['amount']) ? (float) $validated['amount'] : ($payment->transaction?->lines[0]->debit ?? 0);
            $currencyId = $validated['currency_id'] ?? $payment->transaction?->currency_id;
            $rate = isset($validated['rate']) ? (float) $validated['rate'] : ($payment->transaction?->rate ?? 0);
            $bankAccountId = $validated['bank_account_id'] ?? $payment->transaction?->lines[0]->account_id;
            $bankAccount = Account::find($bankAccountId);

            // Editing re-posts the voucher, so its settlements go with it.
            // Leaving them would keep bills closed against an entry that has
            // been deleted.
            $oldTransaction = $payment->transaction()->first();

            if ($oldTransaction) {
                $affected = app(PaymentStatusService::class)->documentsSettledBy($oldTransaction->id);

                Settlement::withoutGlobalScopes()->where('transaction_id', $oldTransaction->id)->forceDelete();
                TransactionLine::where('transaction_id', $oldTransaction->id)->forceDelete();
                Transaction::where('id', $oldTransaction->id)->forceDelete();

                app(PaymentStatusService::class)->recalculatePurchases($affected['purchases']);
            }

            $transaction = app(SettlementService::class)->settle(
                voucher: array_filter([
                    'ledger_id' => $ledger->id,
                    // Money going OUT. Stated by the module, never inferred
                    // from the party — paying a customer (refunding them) is a
                    // real thing, and it must still credit cash.
                    'direction' => SettlementService::DIRECTION_OUT,
                    'date' => $validated['date'],
                    'cash_account_id' => $bankAccountId,
                    'cash_currency_id' => $currencyId,
                    'cash_rate' => $rate,
                    'cash_amount' => $amount,
                    'applied_cash_amount' => $validated['applied_cash_amount'] ?? null,
                    'applied_cash' => $validated['applied_cash'] ?? null,
                    'voucher_number' => $validated['cheque_no'] ?? 'Payment #' . $payment->number,
                    'reference_type' => Payment::class,
                    'reference_id' => $payment->id,
                    'remark' => $validated['narration'] ?? "Payment #{$payment->number} to {$ledger->name}",
                    'remark_fa' => 'پرداخت نقدی #' . $payment->number . ' به ' . $ledger->name,
                    'remark_ps' => $ledger->name . ' ته د #' . $payment->number . ' ورکړه',
                ], fn ($value) => $value !== null),
                allocations: $validated['allocations'] ?? [],
            );

            $this->refreshSettledDocuments($transaction->id);
            $activityLogService->logUpdate(
                reference: $payment,
                before: $beforeState,
                after: [
                    'number' => $payment->number,
                    'date' => $payment->date?->toDateString(),
                    'supplier_name' => $ledger->name,
                    'payment_method' => $bankAccount?->name,
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                ],
                module: 'payment',
                description: "Payment #{$payment->number} updated.",
                metadata: [
                    'action' => 'payment_update',
                    'transaction_id' => $transaction->id,
                ],
            );
        });

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        $redirect = redirect()->route('payments.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.payment')]));

        if ($request->boolean('save_and_print')) {
            $redirect->with('print_url', route('payments.print', $payment));
        }

        return $redirect;
    }

    public function destroy(Request $request, Payment $payment, ActivityLogService $activityLogService)
    {
        $oldValues = [
            'number' => $payment->number,
            'date' => $payment->date?->toDateString(),
            'supplier_name' => $payment->ledger?->name,
            'payment_method' => $payment->transaction?->lines?->first()?->account?->name,
            'amount' => (float) ($payment->transaction?->lines()->max('debit') ?? 0),
            'currency_id' => $payment->transaction?->currency_id,
            'rate' => $payment->transaction?->rate,
        ];

        DB::transaction(function () use ($payment) {
            $transaction = $payment->transaction()->first();
            $affected = ['purchases' => []];

            if ($transaction) {
                // Note which bills this payment was holding closed before the
                // settlements go, so their badges can be re-derived after.
                $affected = app(PaymentStatusService::class)->documentsSettledBy($transaction->id);

                Settlement::withoutGlobalScopes()->where('transaction_id', $transaction->id)->delete();
                $transaction->lines()->delete();
                $transaction->delete();
            }

            $payment->delete();

            app(PaymentStatusService::class)->recalculatePurchases($affected['purchases']);
        });

        $activityLogService->logDelete(
            reference: $payment,
            module: 'payment',
            description: "Payment #{$payment->number} deleted.",
            oldValues: $oldValues,
            metadata: [
                'action' => 'payment_delete',
            ],
        );

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('payments.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.payment')]));
    }

    public function restore(Request $request, Payment $payment, ActivityLogService $activityLogService)
    {
        DB::transaction(function () use ($payment) {
            $transaction = $payment->transaction()->withTrashed()->first();

            if ($transaction) {
                $transaction->restore();
                $transaction->lines()->withTrashed()->restore();
                Settlement::withoutGlobalScopes()
                    ->onlyTrashed()
                    ->where('transaction_id', $transaction->id)
                    ->restore();
            }

            $payment->restore();

            if ($transaction) {
                $this->refreshSettledDocuments($transaction->id);
            }
        });

        $activityLogService->logAction(
            eventType: 'restored',
            reference: $payment,
            module: 'payment',
            description: "Payment #{$payment->number} restored.",
            newValues: [
                'number' => $payment->number,
                'supplier_name' => $payment->ledger?->name,
                'payment_method' => $payment->transaction?->lines?->first()?->account?->name,
            ],
            metadata: [
                'action' => 'payment_restore',
            ],
        );

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('payments.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.payment')]));
    }

    public function forceDelete(Request $request, Payment $payment)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('payments', (string) $payment->id);

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('payments.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.payment')]));
    }

    public function export(Request $request, \App\Services\SpreadsheetExportService $exporter)
    {
        $this->authorize('viewAny', Payment::class);

        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $exportQuery = Payment::with(['ledger', 'transaction.currency', 'transaction.lines.account.accountType'])
            ->search($request->query('search'))
            ->filter($filters);

        $payments = $this->applyCashMovementSort($exportQuery, $sortField, $sortDirection, Payment::class)->get();

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

        $rows = $payments->map(fn ($p) => [
            'number'       => $p->number,
            'ledger_name'  => $p->ledger?->name ?? '-',
            // payment_mode is cast to the PaymentMode enum on the model, so
            // (string) $p->payment_mode is a fatal error, not a value.
            'payment_mode' => PaymentMode::labelFor($p->payment_mode),
            // The cash line, not lines[0]: a settlement voucher carries the
            // ledger relief and any exchange difference alongside the cash,
            // in an order Postgres is free to choose.
            'bank_account' => $accountName($p->bankAccount()),
            'amount'       => $p->paidAmount(),
            'currency'     => $p->transaction?->currency?->code ?? '-',
            'rate'         => $p->transaction?->rate !== null ? (float) $p->transaction->rate : '-',
            'cheque_no'    => $p->cheque_no ?: '-',
            'date'         => $p->date ? $this->dateConversionService->toDisplay($p->date) : '-',
            'narration'    => $p->narration ?: '-',
        ])->all();

        $label = $t('payment', 'payments', 'Payments');

        return $exporter->download([
            'filename'           => 'payments-' . now()->format('Ymd-His') . '.xlsx',
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
                ['key' => 'ledger_name',  'label' => $t('ledger', 'supplier.supplier', 'Supplier'), 'width' => 20],
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

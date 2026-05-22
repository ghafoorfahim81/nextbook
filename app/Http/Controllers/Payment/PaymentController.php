<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\PaymentStoreRequest;
use App\Http\Requests\Payment\PaymentUpdateRequest;
use App\Http\Resources\Payment\PaymentResource;
use App\Enums\PaymentMode;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use App\Models\Payment\Payment;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Services\BillAllocationService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Support\Inertia\CacheKey;
use App\Models\Administration\Currency;
use App\Models\User;
use App\Services\DateConversionService;
use App\Services\ActivityLogService;
use App\Enums\TransactionStatus;
use Illuminate\Support\Facades\Auth;
class PaymentController extends Controller
{
    private $dateConversionService;
    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(Payment::class, 'payment');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $payments = Payment::with(['ledger', 'transaction.currency', 'transaction.lines.account', 'purchasePayments.purchase', 'createdBy', 'updatedBy'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Payments/Index', [
            'payments' => PaymentResource::collection($payments),
            'filterOptions' => [
                'suppliers' => Ledger::query()->where('type', 'supplier')->orderBy('name')->get(['id', 'name']),
                'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name']),
                'bankAccounts' => Account::whereHas('accountType', fn ($q) => $q->whereIn('slug', ['cash-or-bank']))
                    ->orderBy('name')
                    ->get(['id', 'name']),
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
        $latest = Payment::max('number') > 0 ? Payment::max('number') + 1 : 1;
        return inertia('Payments/Create', [
            'latestNumber' => $latest,
            'paymentModes' => collect(PaymentMode::cases())->map(fn (PaymentMode $mode) => [
                'id' => $mode->value,
                'name' => $mode->getLabel(),
            ])->values(),
        ]);
    }

    public function latestNumber(Request $request)
    {
        $latest = Payment::max('number');
        return response()->json([
            'number' => $latest ? ((is_numeric($latest) ? ((int)$latest) : 0) + 1) : 1,
        ]);
    }

    public function store(
        PaymentStoreRequest $request,
        TransactionService $transactionService,
        ActivityLogService $activityLogService
    )
    {
        $payment = DB::transaction(function () use ($request, $transactionService, $activityLogService) {
            $validated = $request->validated();

            $ledger        = Ledger::findOrFail($validated['ledger_id']);
            $amount        = (float) $validated['amount'];
            $currencyId    = $validated['currency_id'];
            $rate          = (float) $validated['rate'];
            $validated['date'] = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null;
            $bankAccountId = $validated['bank_account_id'];
            $paymentMode   = $validated['payment_mode'] ?? PaymentMode::OnAccount->value;
            $bankAccount   = Account::find($bankAccountId);

            $payment = Payment::create([
                'number'       => $validated['number'],
                'date'         => $validated['date'],
                'ledger_id'    => $ledger->id,
                'payment_mode' => $paymentMode,
                'cheque_no'    => $validated['cheque_no'] ?? null,
                'narration'    => $validated['narration'] ?? null,
                'status'       => TransactionStatus::DRAFT->value,
            ]);

            $glAccounts  = Cache::get('gl_accounts');
            $apAccountId = $glAccounts['account-payable'];
            $debitRemark = "Payment #{$payment->number} to {$ledger->name}";

            // Create the GL transaction as DRAFT — it becomes effective only after post()
            $transaction = $transactionService->post(
                header: [
                    'currency_id'    => $currencyId,
                    'rate'           => $rate,
                    'date'           => $validated['date'],
                    'reference_type' => Payment::class,
                    'reference_id'   => $payment->id,
                    'remark'         => $debitRemark,
                    'status'         => TransactionStatus::DRAFT->value,
                ],
                lines: [
                    [
                        'account_id' => $bankAccountId,
                        'debit'      => 0,
                        'credit'     => $amount,
                        'remark'     => 'Payment #' . $payment->number . ' to ' . $ledger->name,
                        'remark_fa'  => 'پرداخت نقدی #' . $payment->number . ' به ' . $ledger->name,
                        'remark_ps'  => $ledger->name . ' ته د #' . $payment->number . ' ورکړه',
                    ],
                    [
                        'account_id' => $apAccountId,
                        'ledger_id'  => $ledger->id,
                        'debit'      => $amount,
                        'credit'     => 0,
                        'remark'     => 'Payment #' . $payment->number . ' to ' . $ledger->name,
                        'remark_fa'  => 'پرداخت نقدی #' . $payment->number . ' به ' . $ledger->name,
                        'remark_ps'  => $ledger->name . ' ته د #' . $payment->number . ' ورکړه',
                    ],
                ],
            );

            app(BillAllocationService::class)->syncPaymentAllocations($payment, $amount, $validated['allocations'] ?? []);

            $activityLogService->logCreate(
                reference: $payment,
                module: 'payment',
                description: "Payment #{$payment->number} created as draft.",
                newValues: [
                    'number'         => $payment->number,
                    'date'           => $payment->date?->toDateString(),
                    'supplier_name'  => $ledger->name,
                    'payment_method' => $bankAccount?->name,
                    'amount'         => $amount,
                    'currency_id'    => $currencyId,
                    'rate'           => $rate,
                    'status'         => TransactionStatus::DRAFT->value,
                ],
                metadata: [
                    'action'         => 'payment_store',
                    'transaction_id' => $transaction->id,
                ],
            );

            return $payment;
        });

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        if ($request->input('create_and_new')) {
            return redirect()->route('payments.create')->with('success', __('general.created_successfully', ['resource' => __('general.resource.payment')]));
        }

        return redirect()->route('payments.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.payment')]));
    }

    public function post(
        Payment $payment,
        ActivityLogService $activityLogService
    ) {
        $this->authorize('update', $payment);

        $statusValue = $payment->status instanceof \BackedEnum
            ? $payment->status->value
            : (string) $payment->status;

        if ($statusValue !== TransactionStatus::DRAFT->value) {
            return back()->with('error', __('general.only_draft_can_be_posted'));
        }

        DB::transaction(function () use ($payment) {
            $transaction = $payment->transaction()->firstOrFail();
            $transaction->update(['status' => TransactionStatus::POSTED->value]);

            $payment->update([
                'status'    => TransactionStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);

            app(BillAllocationService::class)->recalculatePurchasePaymentStatuses(
                $payment->purchasePayments()->pluck('purchase_id')->all()
            );
        });

        $activityLogService->logAction(
            eventType: 'posted',
            reference: $payment,
            module: 'payment',
            description: "Payment #{$payment->number} posted.",
            newValues: ['status' => TransactionStatus::POSTED->value],
            metadata: ['action' => 'payment_post'],
        );

        Cache::forget(CacheKey::forCompanyBranchLocale(request(), 'ledgers'));

        return back()->with('success', __('general.posted_successfully', ['resource' => __('general.resource.payment')]));
    }

    public function reverse(
        Request $request,
        Payment $payment,
        TransactionService $transactionService,
        ActivityLogService $activityLogService
    ) {
        $this->authorize('update', $payment);

        $statusValue = $payment->status instanceof \BackedEnum
            ? $payment->status->value
            : (string) $payment->status;

        if ($statusValue !== TransactionStatus::POSTED->value) {
            return back()->with('error', __('general.only_posted_can_be_reversed'));
        }

        DB::transaction(function () use ($payment, $transactionService, $request) {
            $transaction = $payment->transaction()->firstOrFail();
            $transactionService->reverse($transaction, $request->input('reason'));

            $allocatedPurchaseIds = $payment->purchasePayments()->pluck('purchase_id')->all();
            $payment->purchasePayments()->delete();

            $payment->update([
                'status'      => TransactionStatus::REVERSED->value,
                'reversed_at' => now(),
            ]);

            app(BillAllocationService::class)->recalculatePurchasePaymentStatuses($allocatedPurchaseIds);
        });

        $activityLogService->logAction(
            eventType: 'reversed',
            reference: $payment,
            module: 'payment',
            description: "Payment #{$payment->number} reversed.",
            newValues: ['status' => TransactionStatus::REVERSED->value],
            metadata: ['action' => 'payment_reverse'],
        );

        Cache::forget(CacheKey::forCompanyBranchLocale(request(), 'ledgers'));

        return back()->with('success', __('general.reversed_successfully', ['resource' => __('general.resource.payment')]));
    }

    public function show(Request $request, Payment $payment)
    {
        $payment->load(['ledger', 'transaction.currency', 'transaction.lines.account', 'purchasePayments.purchase', 'createdBy', 'updatedBy']);
        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }

    public function print(Request $request, Payment $payment, ActivityLogService $activityLogService)
    {
        $this->authorize('view', $payment);

        $payment->load([
            'ledger',
            'transaction.currency',
            'transaction.lines.account',
            'transaction.lines.ledger',
            'purchasePayments.purchase',
            'createdBy',
            'updatedBy',
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
        ]);
    }

    public function edit(Request $request, Payment $payment)
    {
        $payment->load(['ledger', 'transaction.currency', 'transaction.lines.account', 'purchasePayments.purchase', 'createdBy', 'updatedBy']);
        return inertia('Payments/Edit', [
            'data' => new PaymentResource($payment),
            'paymentModes' => collect(PaymentMode::cases())->map(fn (PaymentMode $mode) => [
                'id' => $mode->value,
                'name' => $mode->getLabel(),
            ])->values(),
        ]);
    }

    public function update(PaymentUpdateRequest $request, Payment $payment, ActivityLogService $activityLogService)
    {
        $statusValue = $payment->status instanceof \BackedEnum ? $payment->status->value : (string) $payment->status;
        if ($statusValue !== TransactionStatus::DRAFT->value) {
            return back()->with('error', __('general.only_draft_can_be_edited'));
        }

        $beforeState = [
            'number' => $payment->number,
            'date' => $payment->date?->toDateString(),
            'ledger_id' => $payment->ledger_id,
            'amount' => (float) ($payment->transaction?->lines()->max('debit') ?? 0),
            'currency_id' => $payment->transaction?->currency_id,
            'rate' => $payment->transaction?->rate,
        ];

        DB::transaction(function () use ($request, $payment, $activityLogService, $beforeState) {
            $validated = $request->validated();

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
            $glAccounts = Cache::get('gl_accounts');
            $apAccountId = $glAccounts['account-payable'];

            TransactionLine::where('transaction_id', $payment->transaction->id)->forceDelete();
            Transaction::where('id', $payment->transaction->id)->forceDelete();
            $transactionService = app(TransactionService::class);
            $transactionService->post(
                header: [
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => $validated['date'],
                    'reference_type' => Payment::class,
                    'reference_id' => $payment->id,
                ],
                lines: [
                    [
                        'account_id' => $bankAccountId,
                        'debit' => 0,
                        'credit' => $amount,
                        'remark' => 'Payment #' . $payment->number. ' to '.$ledger->name,
                        'remark_fa' => 'پرداخت نقدی #' . $payment->number. ' به '.$ledger->name,
                        'remark_ps' => $ledger->name . ' ته د #' . $payment->number . ' ورکړه',
                   
                    ],
                    [
                        'account_id' => $apAccountId,
                        'ledger_id' => $ledger->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'remark' => 'Payment #' . $payment->number. ' to '.$ledger->name,
                        'remark_fa' => 'پرداخت نقدی #' . $payment->number. ' به '.$ledger->name,
                        'remark_ps' => $ledger->name . ' ته د #' . $payment->number . ' ورکړه',
                    ],
                ],
            );

            app(BillAllocationService::class)->syncPaymentAllocations($payment, $amount, $validated['allocations'] ?? []);
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
        $statusValue = $payment->status instanceof \BackedEnum ? $payment->status->value : (string) $payment->status;
        if ($statusValue !== TransactionStatus::DRAFT->value) {
            return back()->with('error', __('general.only_draft_can_be_deleted'));
        }

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
            $allocatedPurchaseIds = $payment->purchasePayments()->pluck('purchase_id')->all();
            $transaction = $payment->transaction()->first();

            if ($transaction) {
                $transaction->lines()->delete();
                $transaction->delete();
            }

            $payment->purchasePayments()->delete();
            $payment->delete();

            app(BillAllocationService::class)->recalculatePurchasePaymentStatuses($allocatedPurchaseIds);
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
            }

            $payment->restore();
            $payment->purchasePayments()->withTrashed()->restore();
            app(BillAllocationService::class)->recalculatePurchasePaymentStatuses($payment->purchasePayments()->pluck('purchase_id')->all());
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
}

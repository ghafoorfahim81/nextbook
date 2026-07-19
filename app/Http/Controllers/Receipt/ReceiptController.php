<?php

namespace App\Http\Controllers\Receipt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Receipt\ReceiptStoreRequest;
use App\Http\Requests\Receipt\ReceiptUpdateRequest;
use App\Http\Resources\Receipt\ReceiptResource;
use App\Enums\PaymentMode;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use App\Models\Receipt\Receipt;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Services\BillAllocationService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Support\Inertia\CacheKey;
use App\Models\Administration\Currency;
use App\Models\User;
use App\Services\DateConversionService;
use App\Services\ActivityLogService;
class ReceiptController extends Controller
{
    private $dateConversionService;
    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(Receipt::class, 'receipt');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $receipts = Receipt::with(['ledger', 'transaction.currency', 'transaction.lines.account', 'saleReceives.sale', 'createdBy', 'updatedBy'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Receipts/Index', [
            'receipts' => ReceiptResource::collection($receipts),
            'filterOptions' => [
                'customers' => Ledger::query()->where('type', 'customer')->orderBy('name')->get(['id', 'name']),
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
        $latest = Receipt::max('number') > 0 ? Receipt::max('number') + 1 : 1;
        return inertia('Receipts/Create', [
            'latestNumber' => $latest,
            'paymentModes' => collect(PaymentMode::cases())->map(fn (PaymentMode $mode) => [
                'id' => $mode->value,
                'name' => $mode->getLabel(),
            ])->values(),
        ]);
    }

    public function store(
        ReceiptStoreRequest $request,
        TransactionService $transactionService,
        ActivityLogService $activityLogService
    )
    {
        $receipt = DB::transaction(function () use ($request, $transactionService, $activityLogService) {
            $validated = $request->validated();

            $ledger = Ledger::findOrFail($validated['ledger_id']);
            $amount = (float) $validated['amount'];
            $currencyId = $validated['currency_id'];
            $rate = (float) $validated['rate'];
            $bankAccountId = $validated['bank_account_id'];
            $validated['date'] = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null;
            $paymentMode = $validated['payment_mode'] ?? PaymentMode::OnAccount->value;
            $bankAccount = Account::find($bankAccountId);
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null;
            $receipt = Receipt::create([
                'number' => $validated['number'],
                'date' => $validated['date'],
                'ledger_id' => $ledger->id,
                'payment_mode' => $paymentMode,
                'cheque_no' => $validated['cheque_no'] ?? null,
                'narration' => $validated['narration'] ?? null,
            ]);
            $glAccounts = Cache::get('gl_accounts');
            // Credit Accounts Receivable for selected ledger
            $arAccountId = $glAccounts['account-receivable'];

            $creditRemark = "Receipt #{$receipt->number} from {$ledger->name}";

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => $validated['date'],
                    'reference_type' => Receipt::class,
                    'reference_id' => $receipt->id,
                    'remark' => $creditRemark,
                    'voucher_number' => $validated['cheque_no'] ?? 'Receipt #' . $receipt->number,
                ],
                lines: [
                    [
                        'account_id' => $bankAccountId,
                        'debit' => $amount,
                        'credit' => 0,
                        'remark' => 'Cash received #' . $receipt->number. ' from '.$ledger->name,
                        'remark_fa' => 'دریافت نقدی رسید #' . $receipt->number. ' از '.$ledger->name,
                        'remark_ps' => 'د'. '#'. $receipt->number.' '.'د نغدي اخیستلو په اړه رسید له  '.$ledger->name,
                    ],
                    [
                        'account_id' => $arAccountId,
                        'debit' => 0,
                        'ledger_id' => $ledger->id,
                        'credit' => $amount,
                        'remark' => 'Payment by '.$ledger->name.' #' . $receipt->number,
                        'remark_fa' => 'پرداخت توسط '.$ledger->name.' #' . $receipt->number,
                        'remark_ps' => 'د ' . $ledger->name . ' لخوا تادیه #' . $receipt->number,
                    ],
                ],
            );

            app(BillAllocationService::class)->syncReceiptAllocations($receipt, $amount, $validated['allocations'] ?? []);
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
        $receipt->load(['ledger', 'transaction.currency', 'transaction.lines.account', 'saleReceives.sale', 'createdBy', 'updatedBy']);
        return response()->json([
            'data' => new ReceiptResource($receipt),
        ]);
    }

    public function print(Request $request, Receipt $receipt, ActivityLogService $activityLogService)
    {
        $this->authorize('view', $receipt);

        $receipt->load([
            'ledger',
            'transaction.currency',
            'transaction.lines.account',
            'transaction.lines.ledger',
            'saleReceives.sale',
            'createdBy',
            'updatedBy',
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
        $receipt->load(['ledger', 'transaction.currency', 'transaction.lines.account', 'saleReceives.sale', 'createdBy', 'updatedBy']);
        return inertia('Receipts/Edit', [
            'data' => new ReceiptResource($receipt),
            'paymentModes' => collect(PaymentMode::cases())->map(fn (PaymentMode $mode) => [
                'id' => $mode->value,
                'name' => $mode->getLabel(),
            ])->values(),
        ]);
    }

    public function update(ReceiptUpdateRequest $request, Receipt $receipt, ActivityLogService $activityLogService)
    {
        $beforeState = [
            'number' => $receipt->number,
            'date' => $receipt->date?->toDateString(),
            'ledger_id' => $receipt->ledger_id,
            'amount' => (float) ($receipt->transaction?->lines()->max('credit') ?? 0),
            'currency_id' => $receipt->transaction?->currency_id,
            'rate' => $receipt->transaction?->rate,
        ];

        DB::transaction(function () use ($request, $receipt, $activityLogService, $beforeState) {
            $validated = $request->validated();
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
            $glAccounts = Cache::get('gl_accounts');
            $arAccountId = $glAccounts['account-receivable'];
            TransactionLine::where('transaction_id', $receipt->transaction->id)->forceDelete();
             Transaction::where('id', $receipt->transaction->id)->forceDelete();
             $transactionService = app(TransactionService::class);
            $transactionService->post(
                header: [
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'voucher_number' => $validated['cheque_no'] ?? 'Receipt #' . $receipt->number,
                    'date' => $validated['date'],
                    'reference_type' => Receipt::class,
                    'remark'    => $validated['narration'] ?? "Receipt #{$receipt->number} from {$ledger->name}",   
                    'reference_id' => $receipt->id,
                ],
                lines: [
                    [
                        'account_id' => $bankAccountId,
                        'debit' => $amount,
                        'credit' => 0,
                        'remark' => 'Cash received #' . $receipt->number. ' from '.$ledger->name,
                        'remark_fa' => 'دریافت نقدی رسید #' . $receipt->number. ' از '.$ledger->name,
                        'remark_ps' => 'د'. '#'. $receipt->number.' '.'د نغدي اخیستلو په اړه رسید له  '.$ledger->name,
                    ],
                    [
                        'account_id' => $arAccountId,
                        'debit' => 0,
                        'ledger_id' => $ledger->id,
                        'credit' => $amount,
                        'remark' => 'Payment by '.$ledger->name.' #' . $receipt->number,
                        'remark_fa' => 'پرداخت توسط '.$ledger->name.' #' . $receipt->number,
                        'remark_ps' => 'د ' . $ledger->name . ' لخوا تادیه #' . $receipt->number,
                    ],
                ],
            );
            app(BillAllocationService::class)->syncReceiptAllocations($receipt, $amount, $validated['allocations'] ?? []);
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
                    'transaction_id' => $receipt->transaction->id,
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
            $allocatedSaleIds = $receipt->saleReceives()->pluck('sale_id')->all();
            // Soft delete linked transactions then the receipt
                $transaction = $receipt->transaction()->first();

                if ($transaction) {
                    $transaction->lines()->delete();
                    $transaction->delete();
                }

                $receipt->saleReceives()->delete();
                $receipt->delete();

                app(BillAllocationService::class)->recalculateSalePaymentStatuses($allocatedSaleIds);
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
            }

            $receipt->restore();
            $receipt->saleReceives()->withTrashed()->restore();
            app(BillAllocationService::class)->recalculateSalePaymentStatuses($receipt->saleReceives()->pluck('sale_id')->all());
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

        $receipts = Receipt::with(['ledger', 'transaction.currency', 'transaction.lines'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->get();

        $rtl = in_array(app()->getLocale(), ['fa', 'ps'], true);
        $company = $request->user()?->company;
        $companyName = match (app()->getLocale()) {
            'fa'    => $company?->name_fa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            'ps'    => $company?->name_pa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            default => $company?->name_en ?: $company?->abbreviation ?: $company?->name_fa ?: $company?->name_pa ?: config('app.name'),
        };
        $t = fn (string $group, string $key, string $fallback = '') => $exporter->localeTranslation($group, $key, $fallback);

        $rows = $receipts->map(fn ($r) => [
            'number'       => $r->number,
            'ledger_name'  => $r->ledger?->name ?? '-',
            'payment_mode' => PaymentMode::tryFrom((string) $r->payment_mode)?->getLabel() ?? (string) $r->payment_mode,
            'amount'       => (float) ($r->transaction?->lines->first()?->debit > 0
                ? $r->transaction->lines->first()->debit
                : $r->transaction?->lines->first()?->credit ?? 0),
            'currency'     => $r->transaction?->currency?->code ?? '-',
            'date'         => $r->date ? $this->dateConversionService->toDisplay($r->date) : '-',
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
                ['key' => 'amount',       'label' => $t('general', 'amount', 'Amount'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'currency',     'label' => $t('admin', 'currency.currency', 'Currency'), 'width' => 10],
                ['key' => 'date',         'label' => $t('general', 'date', 'Date'), 'width' => 14],
            ],
            'rows' => $rows,
        ]);
    }
}

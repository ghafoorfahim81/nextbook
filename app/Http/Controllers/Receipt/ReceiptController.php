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

    public function store(ReceiptStoreRequest $request, TransactionService $transactionService)
    {
        $receipt = DB::transaction(function () use ($request, $transactionService) {
            $validated = $request->validated();

            $ledger = Ledger::findOrFail($validated['ledger_id']);
            $amount = (float) $validated['amount'];
            $currencyId = $validated['currency_id'];
            $rate = (float) $validated['rate'];
            $bankAccountId = $validated['bank_account_id'];
            $validated['date'] = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null;
            $paymentMode = $validated['payment_mode'] ?? PaymentMode::OnAccount->value;
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

            $transactionService->post(
                header: [
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => $validated['date'],
                    'reference_type' => Receipt::class,
                    'reference_id' => $receipt->id,
                    'remark' => $creditRemark,
                ],
                lines: [
                    [
                        'account_id' => $bankAccountId,
                        'debit' => $amount,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $arAccountId,
                        'debit' => 0,
                        'ledger_id' => $ledger->id,
                        'credit' => $amount,
                    ],
                ],
            );

            app(BillAllocationService::class)->syncReceiptAllocations($receipt, $amount, $validated['allocations'] ?? []);

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

    public function print(Request $request, Receipt $receipt)
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

        return inertia('Vouchers/Print', [
            'voucher' => new ReceiptResource($receipt),
            'company' => auth()->user()?->company,
            'voucherType' => 'receipt',
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

    public function update(ReceiptUpdateRequest $request, Receipt $receipt)
    {
        DB::transaction(function () use ($request, $receipt) {
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
            $glAccounts = Cache::get('gl_accounts');
            $arAccountId = $glAccounts['account-receivable'];
            TransactionLine::where('transaction_id', $receipt->transaction->id)->forceDelete();
             Transaction::where('id', $receipt->transaction->id)->forceDelete();
             $transactionService = app(TransactionService::class);
            $transactionService->post(
                header: [
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => $validated['date'],
                    'reference_type' => Receipt::class,
                    'reference_id' => $receipt->id,
                ],
                lines: [
                    [
                        'account_id' => $bankAccountId,
                        'debit' => $amount,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $arAccountId,
                        'debit' => 0,
                        'ledger_id' => $ledger->id,
                        'credit' => $amount,
                    ],
                ],
            );
            app(BillAllocationService::class)->syncReceiptAllocations($receipt, $amount, $validated['allocations'] ?? []);
            Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        });

        $redirect = redirect()->route('receipts.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.receipt')]));

        if ($request->boolean('save_and_print')) {
            $redirect->with('print_url', route('receipts.print', $receipt));
        }

        return $redirect;
    }

    public function destroy(Request $request, Receipt $receipt)
    {
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
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('receipts.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.receipt')]));
    }
    public function restore(Request $request, Receipt $receipt)
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
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('receipts.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.receipt')]));
    }

}

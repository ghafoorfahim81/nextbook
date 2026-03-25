<?php

namespace App\Http\Controllers\Receipt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Receipt\ReceiptStoreRequest;
use App\Http\Requests\Receipt\ReceiptUpdateRequest;
use App\Http\Resources\Receipt\ReceiptResource;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use App\Models\Receipt\Receipt;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Support\Inertia\CacheKey;
use App\Models\Administration\Currency;
use App\Models\User;
class ReceiptController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Receipt::class, 'receipt');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $receipts = Receipt::with(['ledger', 'transaction.currency', 'transaction.lines.account', 'createdBy', 'updatedBy'])
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
        ]);
    }

    public function store(ReceiptStoreRequest $request, TransactionService $transactionService)
    {
        DB::transaction(function () use ($request, $transactionService) {
            $validated = $request->validated();

            $ledger = Ledger::findOrFail($validated['ledger_id']);
            $amount = (float) $validated['amount'];
            $currencyId = $validated['currency_id'];
            $rate = (float) $validated['rate'];
            $bankAccountId = $validated['bank_account_id'];

            $receipt = Receipt::create([
                'number' => $validated['number'],
                'date' => $validated['date'],
                'ledger_id' => $ledger->id,
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
                    'date' => $receipt->date,
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
        });
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        if ($request->input('create_and_new')) {
            return redirect()->route('receipts.create')->with('success', __('general.created_successfully', ['resource' => __('general.resource.receipt')]));
        }

        return redirect()->route('receipts.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.receipt')]));
    }

    public function show(Request $request, Receipt $receipt)
    {
        $receipt->load(['ledger', 'transaction.currency', 'transaction.lines.account', 'createdBy', 'updatedBy']);
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
        $receipt->load(['ledger', 'transaction.currency', 'transaction.lines.account', 'createdBy', 'updatedBy']);
        return inertia('Receipts/Edit', [
            'data' => new ReceiptResource($receipt),
        ]);
    }

    public function update(ReceiptUpdateRequest $request, Receipt $receipt)
    {
        DB::transaction(function () use ($request, $receipt) {
            $validated = $request->validated();
            $receipt->update([
                'number' => $validated['number'],
                'date' => $validated['date'],
                'ledger_id' => $validated['ledger_id'],
                'cheque_no' => $validated['cheque_no'] ?? null,
                'narration' => $validated['narration'] ?? null,
            ]);

            // Keep accounts aligned and update both transactions
            $ledger = Ledger::findOrFail($receipt->ledger_id);
            $amount = isset($validated['amount']) ? (float) $validated['amount'] : $receipt->amount;
            $currencyId = $validated['currency_id'] ?? $receipt->currency_id;
            $rate = isset($validated['rate']) ? (float) $validated['rate'] : $receipt->rate;
            $date = $validated['date'] ?? $receipt->date;
            $bankAccountId = $validated['bank_account_id'] ?? $receipt->transaction?->lines[0]->account_id;
            $glAccounts = Cache::get('gl_accounts');
            $arAccountId = $glAccounts['account-receivable'];
            TransactionLine::where('transaction_id', $receipt->transaction->id)->forceDelete();
             Transaction::where('id', $receipt->transaction->id)->forceDelete();
             $transactionService = app(TransactionService::class);
             $transaction = $transactionService->post(
                header: [
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => $date,
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
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        });

        return redirect()->route('receipts.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.receipt')]));
    }

    public function destroy(Request $request, Receipt $receipt)
    {
        DB::transaction(function () use ($receipt) {
            // Soft delete linked transactions then the receipt
                $transaction = $receipt->transaction()->first();

                if ($transaction) {
                    $transaction->lines()->delete();
                    $transaction->delete();
                }

                $receipt->delete();
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
        });
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('receipts.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.receipt')]));
    }

}


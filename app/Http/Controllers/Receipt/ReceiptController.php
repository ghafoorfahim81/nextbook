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
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Receipt::class, 'receipt');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');

        $receipts = Receipt::with(['ledger'])
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Receipts/Index', [
            'receipts' => ReceiptResource::collection($receipts),
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
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $validated = $request->validated();
            $validated['date'] = $dateConversionService->toGregorian($validated['date']);

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
            $arAccountId = $glAccounts['accounts-receivable'];

            $creditRemark = "Receipt #{$receipt->number} from {$ledger->name}";
            $creditTxn = $transactionService->createTransaction([
                'account_id' => $arAccountId,
                'amount' => $amount,
                'currency_id' => $currencyId,
                'rate' => $rate,
                'date' => $receipt->date,
                'type' => 'credit',
                'remark' => $creditRemark,
                'reference_type' => 'receipt',
                'reference_id' => $receipt->id,
            ]);


            // Debit selected bank account
            $debitRemark = "Bank receive for receipt #{$receipt->number}";
            $debitTxn = $transactionService->createTransaction([
                'account_id' => $bankAccountId,
                'amount' => $amount,
                'currency_id' => $currencyId,
                'rate' => $rate,
                'date' => $receipt->date,
                'type' => 'debit',
                'remark' => $debitRemark,
                'reference_type' => 'receipt',
                'reference_id' => $receipt->id,
            ]);

            $ledger->ledgerTransactions()->create([
                'transaction_id' => $creditTxn->id,
            ]);

            $receipt->update([
                'receive_transaction_id' => $creditTxn->id,
                'bank_transaction_id' => $creditTxn->id,
            ]);
        });

        if ($request->input('create_and_new')) {
            return redirect()->route('receipts.create')->with('success', __('general.created_successfully', ['resource' => __('general.resource.receipt')]));
        }

        return redirect()->route('receipts.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.receipt')]));
    }

    public function show(Request $request, Receipt $receipt)
    {
        $receipt->load(['ledger', 'receiveTransaction.currency', 'bankTransaction.currency']);
        return response()->json([
            'data' => new ReceiptResource($receipt),
        ]);
    }


    public function edit(Request $request, Receipt $receipt)
    {
        $receipt->load(['ledger', 'receiveTransaction.currency', 'bankTransaction.currency']);
        return inertia('Receipts/Edit', [
            'data' => new ReceiptResource($receipt),
        ]);
    }

    public function update(ReceiptUpdateRequest $request, Receipt $receipt)
    {
        DB::transaction(function () use ($request, $receipt) {
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $validated = $request->validated();

            if (isset($validated['date'])) {
                $validated['date'] = $dateConversionService->toGregorian($validated['date']);
            }

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
            $bankAccountId = $validated['bank_account_id'] ?? $receipt->bankTransaction?->account_id;
            $glAccounts = Cache::get('gl_accounts');
            $arAccountId = $glAccounts['accounts-receivable'];

            if ($receipt->receive_transaction_id) {
                Transaction::where  ('id', $receipt->receive_transaction_id)->update([
                    'account_id' => $arAccountId,
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => $date,
                    'type' => 'credit',
                    'remark' => "Receipt #{$receipt->number} from {$ledger->name}",
                ]);
            }
            if ($receipt->bank_transaction_id) {
                Transaction::where('id', $receipt->bank_transaction_id)->update([
                    'account_id' => $bankAccountId,
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => $date,
                    'type' => 'debit',
                    'remark' => "Bank receive for receipt #{$receipt->number}",
                ]);
            }
        });

        return redirect()->route('receipts.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.receipt')]));
    }

    public function destroy(Request $request, Receipt $receipt)
    {
        DB::transaction(function () use ($receipt) {
            // Soft delete linked transactions then the receipt
            if ($receipt->receive_transaction_id) {
                Transaction::where('id', $receipt->receive_transaction_id)->delete();
                $receipt->ledger->ledgerTransactions()->where('transaction_id', $receipt->receive_transaction_id)->delete();
            }
            if ($receipt->bank_transaction_id) {
                Transaction::where('id', $receipt->bank_transaction_id)->delete();
            }
            $receipt->delete();
        });

        return redirect()->route('receipts.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.receipt')]));
    }
    public function restore(Request $request, Receipt $receipt)
    {
        $receipt->restore();
        if ($receipt->receive_transaction_id) {
            Transaction::where('id', $receipt->receive_transaction_id)->restore();
            $receipt->ledger->ledgerTransactions()->where('transaction_id', $receipt->receive_transaction_id)->restore();
        }
        if ($receipt->bank_transaction_id) {
            Transaction::where('id', $receipt->bank_transaction_id)->restore();
        }
        return redirect()->route('receipts.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.receipt')]));
    }

}



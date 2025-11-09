<?php

namespace App\Http\Controllers\AccountTransfer;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountTransfer\AccountTransferStoreRequest;
use App\Http\Requests\AccountTransfer\AccountTransferUpdateRequest;
use App\Http\Resources\AccountTransfer\AccountTransferResource;
use App\Models\Account\Account;
use App\Models\AccountTransfer\AccountTransfer;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountTransferController extends Controller
{
    public function index(Request $request)
    { 
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');

        $transfers = AccountTransfer::with([
                'fromTransaction.account',
                'fromTransaction.currency',
                'toTransaction.account',
                'toTransaction.currency',
            ])
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('AccountTransfers/Index', [
            'transfers' => AccountTransferResource::collection($transfers),
        ]);
    }

    public function create(Request $request)
    { 
        return inertia('AccountTransfers/Create');
    }


    public function store(AccountTransferStoreRequest $request, TransactionService $transactionService)
    { 
        // dd((string) \Symfony\Component\Uid\Ulid::generate());
        DB::transaction(function () use ($request, $transactionService) {
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $validated = $request->validated();
            $validated['date'] = $dateConversionService->toGregorian($validated['date']);

            $fromAccountId = $validated['from_account_id'];
            $toAccountId = $validated['to_account_id'];
            $amount = (float) $validated['amount'];
            $currencyId = $validated['currency_id'];
            $rate = (float) $validated['rate'];

            // Use any existing ledger to fulfill the non-null FK constraint on transactions table.
            // Since this is an internal transfer, we associate both transactions with the same placeholder ledger.
            $ledger = Ledger::query()->latest()->first();
            if (!$ledger) {
                abort(422, 'No ledger found to associate the transfer transactions.');
            }

            $transfer = AccountTransfer::create([
                'number' => $validated['number'] ?? null,
                'date' => $validated['date'],
                'remark' => $validated['remark'] ?? null, 
            ]);

            // CREDIT from the source account
            $creditTxn = $transactionService->createTransaction([
                'account_id' => $fromAccountId,
                'ledger_id' => null,
                'amount' => $amount,
                'currency_id' => $currencyId,
                'rate' => $rate,
                'date' => $transfer->date,
                'type' => 'credit',
                'remark' => "Transfer #{$transfer->number} - Credit from source account",
                'reference_type' => 'account_transfer',
                'reference_id' => $transfer->id,
            ]);

            // DEBIT to the destination account
            $debitTxn = $transactionService->createTransaction([
                'account_id' => $toAccountId,
                'ledger_id' => null,
                'amount' => $amount,
                'currency_id' => $currencyId,
                'rate' => $rate,
                'date' => $transfer->date,
                'type' => 'debit',
                'remark' => "Transfer #{$transfer->number} - Debit to destination account",
                'reference_type' => 'account_transfer',
                'reference_id' => $transfer->id,
            ]);

            $transfer->update([
                'from_transaction_id' => $creditTxn->id,
                'to_transaction_id' => $debitTxn->id,
            ]);
        });

        if ($request->input('create_and_new')) {
            return redirect()->route('account-transfers.create')->with('success', 'Account Transfer created successfully.');
        }

        return redirect()->route('account-transfers.index')->with('success', 'Account Transfer created successfully.');
    }

    public function show(Request $request, AccountTransfer $accountTransfer)
    {
        $accountTransfer->load(['fromTransaction.account', 'fromTransaction.currency', 'toTransaction.account', 'toTransaction.currency']);
        return response()->json([
            'data' => new AccountTransferResource($accountTransfer),
        ]);
    }

    public function edit(Request $request, AccountTransfer $accountTransfer)
    {
        $accountTransfer->load(['fromTransaction.account', 'fromTransaction.currency', 'toTransaction.account', 'toTransaction.currency']);
        return inertia('AccountTransfers/Edit', [
            'data' => new AccountTransferResource($accountTransfer),
        ]);
    }

    public function update(AccountTransferUpdateRequest $request, AccountTransfer $accountTransfer)
    { 
        DB::transaction(function () use ($request, $accountTransfer) {
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $validated = $request->validated();

            if (isset($validated['date'])) {
                $validated['date'] = $dateConversionService->toGregorian($validated['date']);
            }

            $accountTransfer->update([
                'number' => $validated['number'] ?? $accountTransfer->number,
                'date' => $validated['date'] ?? $accountTransfer->date,
                'remark' => $validated['remark'] ?? $accountTransfer->remark,
            ]);

            $amount = isset($validated['amount']) ? (float) $validated['amount'] : ($accountTransfer->toTransaction?->amount ?? $accountTransfer->fromTransaction?->amount);
            $currencyId = $validated['currency_id'] ?? ($accountTransfer->toTransaction?->currency_id ?? $accountTransfer->fromTransaction?->currency_id);
            $rate = isset($validated['rate']) ? (float) $validated['rate'] : ($accountTransfer->toTransaction?->rate ?? $accountTransfer->fromTransaction?->rate);
            $date = $validated['date'] ?? $accountTransfer->date;
            $fromAccountId = $validated['from_account_id'] ?? $accountTransfer->fromTransaction?->account_id;
            $toAccountId = $validated['to_account_id'] ?? $accountTransfer->toTransaction?->account_id;

            if ($accountTransfer->from_transaction_id) {
                Transaction::where('id', $accountTransfer->from_transaction_id)->update([
                    'account_id' => $fromAccountId,
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => $date,
                    'type' => 'credit',
                    'remark' => "Transfer #{$accountTransfer->number} - Credit from source account",
                ]);
            }

            if ($accountTransfer->to_transaction_id) {
                Transaction::where('id', $accountTransfer->to_transaction_id)->update([
                    'account_id' => $toAccountId,
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => $date,
                    'type' => 'debit',
                    'remark' => "Transfer #{$accountTransfer->number} - Debit to destination account",
                ]);
            }
        });

        return redirect()->route('account-transfers.index')->with('success', 'Account Transfer updated successfully.');
    }

    public function destroy(AccountTransfer $accountTransfer)
    { 
        $accountTransfer->delete();
        return back()->with('success', 'Account Transfer deleted.');
    }

    public function restore(AccountTransfer $accountTransfer)
    { 
        $accountTransfer->restore();
        return back()->with('success', 'Account Transfer restored.');
    }
}



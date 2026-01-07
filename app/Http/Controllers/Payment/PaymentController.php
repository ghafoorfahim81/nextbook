<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\PaymentStoreRequest;
use App\Http\Requests\Payment\PaymentUpdateRequest;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use App\Models\Payment\Payment;
use App\Models\Transaction\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
class PaymentController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Payment::class, 'payment');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');

        $payments = Payment::with(['ledger'])
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Payments/Index', [
            'payments' => PaymentResource::collection($payments),
        ]);
    }

    public function create(Request $request)
    {
        $latest = Payment::max('number') > 0 ? Payment::max('number') + 1 : 1;
        return inertia('Payments/Create', [
            'latestNumber' => $latest,
        ]);
    }

    public function latestNumber(Request $request)
    {
        $latest = Payment::max('number');
        return response()->json([
            'number' => $latest ? ((is_numeric($latest) ? ((int)$latest) : 0) + 1) : 1,
        ]);
    }

    public function store(PaymentStoreRequest $request, TransactionService $transactionService)
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

            $payment = Payment::create([
                'number' => $validated['number'],
                'date' => $validated['date'],
                'ledger_id' => $ledger->id,
                'cheque_no' => $validated['cheque_no'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            // Debit Accounts Payable for selected ledger (reduce liability)
            $glAccounts = Cache::get('gl_accounts');
            $apAccountId = $glAccounts['accounts-payable'];
            $debitRemark = "Payment #{$payment->number} to {$ledger->name}";
            $debitTxn = $transactionService->createTransaction([
                'account_id' => $apAccountId,
                'amount' => $amount,
                'currency_id' => $currencyId,
                'rate' => $rate,
                'date' => $payment->date,
                'type' => 'debit',
                'remark' => $debitRemark,
                'reference_type' => 'payment',
                'reference_id' => $payment->id,
            ]);

            // Credit selected bank account
            $creditRemark = "Bank payment for payment #{$payment->number}";
            $creditTxn = $transactionService->createTransaction([
                'account_id' => $bankAccountId,
                'amount' => $amount,
                'currency_id' => $currencyId,
                'rate' => $rate,
                'date' => $payment->date,
                'type' => 'credit',
                'remark' => $creditRemark,
                'reference_type' => 'payment',
                'reference_id' => $payment->id,
            ]);

            $ledger->ledgerTransactions()->create([
                'transaction_id' => $debitTxn->id,
            ]);

            $payment->update([
                'payment_transaction_id' => $debitTxn->id,
                'bank_transaction_id' => $creditTxn->id,
            ]);
        });

        if ($request->input('create_and_new')) {
            return redirect()->route('payments.create')->with('success', 'Payment created successfully.');
        }

        return redirect()->route('payments.index')->with('success', 'Payment created successfully.');
    }

    public function show(Request $request, Payment $payment)
    {
        $payment->load(['ledger', 'paymentTransaction.currency', 'bankTransaction.currency', 'bankTransaction.account']);
        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }

    public function edit(Request $request, Payment $payment)
    {
        $payment->load(['ledger', 'paymentTransaction.currency', 'bankTransaction.currency', 'bankTransaction.account']);
        return inertia('Payments/Edit', [
            'data' => new PaymentResource($payment),
        ]);
    }

    public function update(PaymentUpdateRequest $request, Payment $payment)
    {
        DB::transaction(function () use ($request, $payment) {
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $validated = $request->validated();

            if (isset($validated['date'])) {
                $validated['date'] = $dateConversionService->toGregorian($validated['date']);
            }

            $payment->update([
                'number' => $validated['number'] ?? $payment->number,
                'date' => $validated['date'] ?? $payment->date,
                'ledger_id' => $validated['ledger_id'] ?? $payment->ledger_id,
                'cheque_no' => $validated['cheque_no'] ?? $payment->cheque_no,
                'description' => $validated['description'] ?? $payment->description,
            ]);

            $ledger = Ledger::findOrFail($payment->ledger_id);
            $amount = isset($validated['amount']) ? (float) $validated['amount'] : ($payment->bankTransaction?->amount ?? 0);
            $currencyId = $validated['currency_id'] ?? $payment->bankTransaction?->currency_id;
            $rate = isset($validated['rate']) ? (float) $validated['rate'] : ($payment->bankTransaction?->rate ?? 0);
            $date = $validated['date'] ?? $payment->date;
            $bankAccountId = $validated['bank_account_id'] ?? $payment->bankTransaction?->account_id;
            $glAccounts = Cache::get('gl_accounts');
            $apAccountId = $glAccounts['accounts-payable'];

            if ($payment->payment_transaction_id) {
                Transaction::where('id', $payment->payment_transaction_id)->update([
                    'account_id' => $apAccountId,
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => $date,
                    'type' => 'debit',
                    'remark' => "Payment #{$payment->number} to {$ledger->name}",
                ]);
            }

            if ($payment->bank_transaction_id) {
                Transaction::where('id', $payment->bank_transaction_id)->update([
                    'account_id' => $bankAccountId,
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => $date,
                    'type' => 'credit',
                    'remark' => "Bank payment for payment #{$payment->number}",
                ]);
            }
        });

        return redirect()->route('payments.index')->with('success', 'Payment updated successfully.');
    }

    public function destroy(Request $request, Payment $payment)
    {
        DB::transaction(function () use ($payment) {
            if ($payment->payment_transaction_id) {
                Transaction::where('id', $payment->payment_transaction_id)->delete();
                $payment->ledger->ledgerTransactions()->where('transaction_id', $payment->payment_transaction_id)->delete();
            }
            if ($payment->bank_transaction_id) {
                Transaction::where('id', $payment->bank_transaction_id)->delete();
            }
            $payment->delete();
        });

        return redirect()->route('payments.index')->with('success', 'Payment deleted successfully.');
    }

    public function restore(Request $request, Payment $payment)
    {
        $payment->restore();
        if ($payment->payment_transaction_id) {
            Transaction::where('id', $payment->payment_transaction_id)->restore();
            $payment->ledger->ledgerTransactions()->where('transaction_id', $payment->payment_transaction_id)->restore();
        }
        if ($payment->bank_transaction_id) {
            Transaction::where('id', $payment->bank_transaction_id)->restore();
        }
        return redirect()->route('payments.index')->with('success', 'Payment restored successfully.');
    }
}



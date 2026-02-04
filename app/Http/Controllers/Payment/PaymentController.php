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
use App\Models\Transaction\TransactionLine;
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

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => $payment->date,
                    'reference_type' => Payment::class,
                    'reference_id' => $payment->id,
                    'remark' => $debitRemark,
                ],
                lines: [
                    [
                        'account_id' => $bankAccountId,
                        'debit' => 0,
                        'credit' => $amount,
                    ],
                    [
                        'account_id' => $apAccountId,
                        'ledger_id' => $ledger->id,
                        'debit' => $amount,
                        'credit' => 0,
                    ],

                ],
            );

            $payment->update([
                'transaction_id' => $transaction->id,
            ]);
        });

        if ($request->input('create_and_new')) {
            return redirect()->route('payments.create')->with('success', __('general.created_successfully', ['resource' => __('general.resource.payment')]));
        }

        return redirect()->route('payments.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.payment')]));
    }

    public function show(Request $request, Payment $payment)
    {
        $payment->load(['ledger', 'transaction.currency', 'transaction.lines.account']);
        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }

    public function edit(Request $request, Payment $payment)
    {
        $payment->load(['ledger', 'transaction.currency', 'transaction.lines.account']);
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
            $amount = isset($validated['amount']) ? (float) $validated['amount'] : ($payment->transaction?->lines[0]->debit ?? 0);
            $currencyId = $validated['currency_id'] ?? $payment->transaction?->currency_id;
            $rate = isset($validated['rate']) ? (float) $validated['rate'] : ($payment->transaction?->rate ?? 0);
            $date = $validated['date'] ?? $payment->date;
            $bankAccountId = $validated['bank_account_id'] ?? $payment->transaction?->lines[0]->account_id;
            $glAccounts = Cache::get('gl_accounts');
            $apAccountId = $glAccounts['accounts-payable'];

            TransactionLine::where('transaction_id', $payment->transaction_id)->forceDelete();
            Transaction::where('id', $payment->transaction_id)->forceDelete();
            $transactionService = app(TransactionService::class);
            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => $date,
                    'reference_type' => Payment::class,
                    'reference_id' => $payment->id,
                ],
                lines: [
                    [
                        'account_id' => $bankAccountId,
                        'debit' => 0,
                        'credit' => $amount,
                    ],
                    [
                        'account_id' => $apAccountId,
                        'debit' => $amount,
                        'credit' => 0,
                    ],
                ],
            );
            $payment->update([
                'transaction_id' => $transaction->id,
            ]);
        });

        return redirect()->route('payments.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.payment')]));
    }

    public function destroy(Request $request, Payment $payment)
    {
        DB::transaction(function () use ($payment) {

            TransactionLine::where('transaction_id', $payment->transaction_id)->delete();
            Transaction::where('id', $payment->transaction_id)->delete();
            $payment->delete();
        });

        return redirect()->route('payments.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.payment')]));
    }

    public function restore(Request $request, Payment $payment)
    {
        $payment->restore();
        TransactionLine::where('transaction_id', $payment->transaction_id)->restore();
        Transaction::where('id', operator: $payment->transaction_id)->restore();
        return redirect()->route('payments.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.payment')]));
    }
}



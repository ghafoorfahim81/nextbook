<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\LedgerStoreRequest;
use App\Http\Requests\Ledger\LedgerUpdateRequest;
use App\Http\Resources\Ledger\LedgerResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Administration\BranchResource;
use App\Http\Resources\Sale\SaleResource;
use App\Http\Resources\Receipt\ReceiptResource;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Ledger\Ledger;
use App\Models\Ledger\LedgerTransaction;
use App\Models\Transaction\Transaction;
use App\Models\Administration\Currency;
use App\Models\Administration\Branch;
use Illuminate\Http\Request;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Cache;
use App\Models\Transaction\TransactionLine;
use Illuminate\Support\Facades\DB;
class CustomerController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Ledger::class, 'customer');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $type = $request->input('type', 'customer'); // default to customer

        $customers = Ledger::search($request->query('search'))
            ->where('type', $type) // Filter by type
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Ledgers/Customers/Index', [
            'customers' => LedgerResource::collection($customers),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('Ledgers/Customers/Create', [
            'currencies' => CurrencyResource::collection(Currency::orderBy('name')->get()),
            'branches' => BranchResource::collection(Branch::orderBy('name')->get()),
            'accountTypes' => [],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LedgerStoreRequest $request)
    {
        $validated = $request->validated();
        $validated['type'] = 'customer';
        $ledger = Ledger::create($validated);
        $glAccounts = Cache::get('gl_accounts');
        $transactionService = app(TransactionService::class);
        if ($validated['opening_currency_id'] && $validated['amount'] && $validated['amount'] > 0) {

            $arId = $glAccounts['accounts-receivable'];
            $equityId = $glAccounts['opening-balance-equity'];

            abort_unless($arId && $equityId, 500, 'System accounts (AR/AP) are missing.');

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $validated['opening_currency_id'],
                    'rate' => (float) $validated['rate'],
                    'date' => now(),
                    'reference_type' => Ledger::class,
                    'reference_id' => $ledger->id,
                    'remark' => 'Opening balance for customer ' . $ledger->name,
                ],
                lines: [
                ['account_id' => $arId, 'ledger_id' => $ledger->id, 'debit' => (float) $validated['amount'], 'credit' => 0, 'remark' => 'Opening balance for customer ' . $ledger->name],
                ['account_id' => $equityId, 'debit' => 0, 'credit' => (float) $validated['amount'], 'remark' => 'Opening balance for customer ' . $ledger->name],
            ]);

            // $ledger->ledgerTransactions()->create([
            //     'transaction_id' => $transaction['id'],
            // ]);

            $transaction->opening()->create([
                'ledgerable_id' => $ledger->id,
                'ledgerable_type' => 'ledger',
            ]);
        }
        if ($request->boolean('stay') || $request->boolean('create_and_new')) {
            return to_route('customers.create')
                ->with('success', __('general.created_successfully', ['resource' => __('general.resource.customer')]));
        }

        return to_route('customers.index')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.customer')]));

    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Ledger $customer)
    {
        $customer->load([
            'currency',
            'opening',
            'opening.transaction.currency',
            'opening.transaction.lines',
            'transactionLines.transaction',
            'transactionLines.transaction.currency',
        ]);

        $sales = $customer->sales->load('transaction.currency');
        $receipts = $customer->receipts->load('receiveTransaction.currency');
        $payments = $customer->payments->load('bankTransaction.currency');
        if ($request->expectsJson()) {
            return response()->json([
                'customer' => new LedgerResource($customer),
                'sales' => SaleResource::collection($sales),
                'receipts' => ReceiptResource::collection($receipts),
                'payments' => PaymentResource::collection($payments),
            ]);
        }

        return inertia('Ledgers/Customers/Show', [
            'customer' => new LedgerResource($customer),
            'sales' => SaleResource::collection($sales),
            'receipts' => ReceiptResource::collection($receipts),
            'payments' => PaymentResource::collection($payments),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ledger $customer)
    {
        $customer->load(['currency', 'opening', 'opening.transaction.currency','opening.transaction.lines']);
        return inertia('Ledgers/Customers/Edit', [
            'customer' => new LedgerResource($customer),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LedgerUpdateRequest $request, Ledger $customer)
    {
        $validated = $request->validated();
        $customer->update($validated);

        // Remove existing opening balances

        if($customer->opening) {
            TransactionLine::where('transaction_id',$customer->opening->transaction_id)->forceDelete();
            $customer->opening->forceDelete();
            $customer->opening->transaction()->forceDelete();
        }


        if ($validated['amount'] && $validated['amount'] > 0 && $validated['opening_currency_id'] && $validated['rate']) {  // Update existing opening balances
            $glAccounts = Cache::get('gl_accounts');
            $arId = $glAccounts['accounts-receivable'];
            $equityId = $glAccounts['opening-balance-equity'];
            $transactionService = app(TransactionService::class);
            abort_unless($arId && $equityId, 500, 'System accounts (AR/AP) are missing.');

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $validated['opening_currency_id'],
                    'rate' => (float) $validated['rate'],
                    'date' => now(),
                    'reference_type' => Ledger::class,
                    'reference_id' => $customer->id,
                    'remark' => 'Opening balance for customer ' . $customer->name,
                ],
                lines: [
                ['account_id' => $arId, 'ledger_id' => $customer->id, 'debit' => (float) $validated['amount'], 'credit' => 0, 'remark' => 'Opening balance for customer ' . $customer->name],
                ['account_id' => $equityId, 'debit' => 0, 'credit' => (float) $validated['amount'], 'remark' => 'Opening balance for customer ' . $customer->name],
            ]);

            $transaction->opening()->create([
                'ledgerable_id' => $customer->id,
                'ledgerable_type' => 'ledger',
            ]);
        }

        return to_route('customers.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.customer')]));
    }

    /**
     * Remove the specified resource from storage.
     */


    public function destroy(Request $request, Ledger $customer)
    {
        $openingTransactionId = $customer->opening?->transaction_id;

        // Allow delete only when customer has no transactions OR only opening transaction.
        $hasNonOpeningTransactions = TransactionLine::query()
            ->where('ledger_id', $customer->id)
            ->when(
                $openingTransactionId,
                fn ($q) => $q->where('transaction_id', '!=', $openingTransactionId),
                fn ($q) => $q // no opening found -> any transaction means blocked
            )
            ->exists();

        if ($hasNonOpeningTransactions) {
            return back()->with('error', __('Cannot delete customer: this customer has transactions. Please remove related transactions first.'));
        }

        DB::transaction(function () use ($customer, $openingTransactionId) {
            if ($openingTransactionId) {
                // Delete the whole opening transaction (both lines) and the opening record.
                TransactionLine::where('transaction_id', $openingTransactionId)->delete();
                Transaction::where('id', $openingTransactionId)->delete();
                $customer->opening()->delete();
            }

            $customer->delete();
        });

        return redirect()
            ->route('customers.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.customer')]));
    }

    public function restore(Request $request, Ledger $customer)
    {
        $opening = $customer->opening()->withTrashed()->first();
        $openingTransactionId = $opening?->transaction_id;

        DB::transaction(function () use ($customer, $openingTransactionId) {
            if ($openingTransactionId) {
                Transaction::withTrashed()->where('id', $openingTransactionId)->restore();
                TransactionLine::withTrashed()->where('transaction_id', $openingTransactionId)->restore();
                $customer->opening()->withTrashed()->restore();
            }

            $customer->restore();
        });
        return redirect()->route('customers.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.customer')]));
    }
}

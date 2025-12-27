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
        $openings = collect($request->input('openings', []))
            ->filter(function ($opening) {
                return !empty($opening['currency_id']) && (float)($opening['amount'] ?? 0) > 0;
            });
        $glAccounts = Cache::get('gl_accounts');
        $transactionService = app(TransactionService::class);
        if ($openings->isNotEmpty()) {
            $arId = $glAccounts['account-receivable'];
            $apId = $glAccounts['account-payable'];

            abort_unless($arId && $apId, 500, 'System accounts (AR/AP) are missing.');

            $openings->each(function ($opening) use ($ledger, $arId, $apId, $transactionService) {
                $type = $opening['type'] ?? 'debit';
                $accountId = $type === 'credit' ? $arId : $apId;
                $data = [
                    'ledger' =>$ledger,
                    'account_id' => $accountId,
                    'amount' => (float) $opening['amount'],
                    'currency_id' => $opening['currency_id'],
                    'rate' => (float) $opening['rate'],
                    'date' => now(),
                    'type' => $type,
                ];

                $transaction = $transactionService->createLedgerTransaction($data);

                $ledger->ledgerTransactions()->create([
                    'transaction_id' => $transaction['id'],
                ]);

                $transaction->opening()->create([
                    'ledgerable_id' => $ledger->id,
                    'ledgerable_type' => 'ledger',
                ]);
            });
        }
        return to_route('customers.index')->with('success', 'Customer created successfully.');

    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Ledger $customer)
    {
        $customer->load([
            'currency',
            'openings.transaction.currency',
            'ledgerTransactions.transaction.account',
            'ledgerTransactions.transaction.currency',
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
        $customer->load(['currency', 'branch', 'openings.transaction.currency']);

        $transactionTypes = [
            ['id' => 'debit', 'name' => 'Debit'],
            ['id' => 'credit', 'name' => 'Credit'],
        ];

        return inertia('Ledgers/Customers/Edit', [
            'customer' => new LedgerResource($customer),
            'currencies' => CurrencyResource::collection(Currency::orderBy('name')->get()),
            'branches' => BranchResource::collection(Branch::orderBy('name')->get()),
            'transactionTypes' => $transactionTypes,
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
        $customer->openings->each(function ($opening) {
            LedgerTransaction::where('transaction_id',$opening->transaction_id)->forceDelete();
            $opening->forceDelete();
            $opening->transaction()->forceDelete();
        });

        $openings = collect($request->input('openings', []))
            ->filter(function ($opening) {
                return !empty($opening['currency_id']) && (float)($opening['amount'] ?? 0) > 0;
            });
            $glAccounts = Cache::get('gl_accounts');

        if ($openings->isNotEmpty()) {  // Update existing opening balances
            $arId = $glAccounts['account-receivable'];
            $apId = $glAccounts['account-payable'];

            abort_unless($arId && $apId, 500, 'System accounts (AR/AP) are missing.');
            $transactionService = app(TransactionService::class);
            $openings->each(function ($opening) use ($customer, $arId, $apId, $transactionService) {
                $type = $opening['type'] ?? 'debit';
                $accountId = $type === 'credit' ? $arId : $apId;
                $data = [
                    'ledger' =>$customer,
                    'account_id' => $accountId,
                    'amount' => (float) $opening['amount'],
                    'currency_id' => $opening['currency_id'],
                    'rate' => (float) $opening['rate'],
                    'date' => now(),
                    'type' => $type,
                ];

                $transaction = $transactionService->createLedgerTransaction($data);

                $customer->ledgerTransactions()->create([
                    'transaction_id' => $transaction['id'],
                ]);

                $transaction->opening()->create([
                    'ledgerable_id' => $customer->id,
                    'ledgerable_type' => 'ledger',
                ]);
            });
        }

        return to_route('customers.index')->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Ledger $customer)
    {

        if (!$customer->canBeDeleted()) {
            return inertia('Ledgers/Customers/Index', [
                'error' => $customer->getDependencyMessage()
            ]);
        }
        $customer->ledgerTransactions()->get()->each(function ($transaction) {
            $transaction->transaction()->delete();
            $transaction->delete();
        });
        $customer->openings()->delete();
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }
    public function restore(Request $request, Ledger $customer)
    {
        \DB::transaction(function () use ($customer) {
            // 3. Batch restore instead of one-by-one
            $customer->ledgerTransactions()
                ->with(['transaction']) // Eager load to avoid N+1
                ->onlyTrashed()
                ->get()
                ->each(function ($ledgerTransaction) {
                    if ($ledgerTransaction->transaction) {
                        $ledgerTransaction->transaction()->restore();
                    }
                    $ledgerTransaction->restore();
                });

            // 4. Batch restore openings
            $customer->openings()
                ->onlyTrashed()
                ->restore();

            // 5. Restore the customer
            $customer->restore();
        });

        return redirect()->route('customers.index')
            ->with('success', 'Customer restored successfully.');
    }
}

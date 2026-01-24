<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\LedgerStoreRequest;
use App\Http\Requests\Ledger\LedgerUpdateRequest;
use App\Http\Resources\Ledger\LedgerResource;
use App\Http\Resources\Transaction\TransactionResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Administration\BranchResource;
use App\Http\Resources\Receipt\ReceiptResource;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use App\Models\Administration\Currency;
use App\Models\Administration\Branch;
use App\Http\Resources\Purchase\PurchaseResource;
use App\Http\Resources\Payment\PaymentResource;
use Illuminate\Http\Request;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Cache;
use App\Http\Resources\Sale\SaleResource;
use App\Models\Ledger\LedgerTransaction;

class SupplierController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Ledger::class, 'supplier');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $type = $request->input('type', 'supplier'); // default to supplier

        $suppliers = Ledger::search($request->query('search'))
            ->where('type', $type) // Filter by type
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Ledgers/Suppliers/Index', [
            'suppliers' => LedgerResource::collection($suppliers),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('Ledgers/Suppliers/Create', [
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
        $validated['type'] = 'supplier';
        $ledger = Ledger::create($validated);
        $openings = collect($request->input('openings', []))
            ->filter(function ($opening) {
                return !empty($opening['currency_id']) && (float)($opening['amount'] ?? 0) > 0;
            });
        $glAccounts = Cache::get('gl_accounts');
        $transactionService = app(TransactionService::class);
            if ($openings->isNotEmpty()) {
                $arId = $glAccounts['accounts-receivable'];
                $apId = $glAccounts['accounts-payable'];
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

        if ($request->boolean('stay') || $request->boolean('create_and_new')) {
            return to_route('suppliers.create')
                ->with('success', __('general.created_successfully', ['resource' => __('general.resource.supplier')]));
        }

        return to_route('suppliers.index')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.supplier')]));
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Ledger $supplier)
    {
        $supplier->load([
            'currency',
            'openings.transaction.currency',
            'ledgerTransactions.transaction.account',
            'ledgerTransactions.transaction.currency',
        ]);

        $sales = $supplier->sales->load('transaction.currency');
        $receipts = $supplier->receipts->load('receiveTransaction.currency');
        $payments = $supplier->payments->load('bankTransaction.currency');
        if ($request->expectsJson()) {
            return response()->json([
                'supplier' => new LedgerResource($supplier),
                'sales' => SaleResource::collection($sales),
                'receipts' => ReceiptResource::collection($receipts),
                'payments' => PaymentResource::collection($payments),
            ]);
        }

        return inertia('Ledgers/Suppliers/Show', [
            'supplier' => new LedgerResource($supplier),
            'purchases' => PurchaseResource::collection($purchases),
            'payments' => PaymentResource::collection($payments),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Ledger $supplier)
    {
        $supplier->load(['currency', 'branch', 'openings.transaction.currency']);

        $transactionTypes = [
            ['id' => 'debit', 'name' => 'Debit'],
            ['id' => 'credit', 'name' => 'Credit'],
        ];

        return inertia('Ledgers/Suppliers/Edit', [
            'supplier' => new LedgerResource($supplier),
            'currencies' => CurrencyResource::collection(Currency::orderBy('name')->get()),
            'branches' => BranchResource::collection(Branch::orderBy('name')->get()),
            'transactionTypes' => $transactionTypes,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LedgerUpdateRequest $request, Ledger $supplier)
    {
        $validated = $request->validated();
        $supplier->update($validated);

        // Remove existing opening balances

        $supplier->openings->each(function ($opening) {
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
            $arId = $glAccounts['accounts-receivable'];
            $apId = $glAccounts['accounts-payable'];
  // Update existing opening balances
            abort_unless($arId && $apId, 500, 'System accounts (AR/AP) are missing.');

            $transactionService = app(TransactionService::class);
            $openings->each(function ($opening) use ($supplier, $arId, $apId, $transactionService) {
                $type = $opening['type'] ?? 'debit';
                $accountId = $type === 'credit' ? $arId : $apId;
                $data = [
                    'ledger' =>$supplier,
                    'account_id' => $accountId,
                    'amount' => (float) $opening['amount'],
                    'currency_id' => $opening['currency_id'],
                    'rate' => (float) $opening['rate'],
                    'date' => now(),
                    'type' => $type,
                ];

                $transaction = $transactionService->createLedgerTransaction($data);

                $supplier->ledgerTransactions()->create([
                    'transaction_id' => $transaction['id'],
                ]);

                $transaction->opening()->create([
                    'ledgerable_id' => $supplier->id,
                    'ledgerable_type' => 'ledger',
                ]);
            });
        }

        return to_route('suppliers.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.supplier')]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Ledger $supplier)
    {

        if (!$supplier->canBeDeleted()) {
            return inertia('Ledgers/Suppliers/Index', [
                'error' => $supplier->getDependencyMessage()
            ]);
        }
        $supplier->openings->each(function ($opening) {
            LedgerTransaction::where('transaction_id',$opening->transaction_id)->delete();
            $opening->delete();
            $opening->transaction()->delete();
        });
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.supplier')]));
    }
    public function restore(Request $request, Ledger $supplier)
    {
        $supplier->openings->each(function ($opening) {
            LedgerTransaction::where('transaction_id',$opening->transaction_id)->restore();
            $opening->restore();
            $opening->transaction()->restore();
        });

        $supplier->restore();
        return redirect()->route('suppliers.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.supplier')]));
    }
}

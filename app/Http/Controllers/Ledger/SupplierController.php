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
use App\Models\Transaction\TransactionLine;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction\Transaction;
use App\Support\Inertia\CacheKey;
use App\Models\User;

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
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $type = $request->input('type', 'supplier'); // default to supplier

        $suppliers = Ledger::search($request->query('search'))
            ->where('type', $type) // Filter by type
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Ledgers/Suppliers/Index', [
            'suppliers' => LedgerResource::collection($suppliers),
            'filterOptions' => [
                'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name']),
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
        $glAccounts = Cache::get('gl_accounts');
        $transactionService = app(TransactionService::class);
        if ($validated['opening_currency_id'] && $validated['amount'] && $validated['amount'] > 0) {

            $equityId = $glAccounts['opening-balance-equity'];
            $apId = $glAccounts['accounts-payable'];

            abort_unless($equityId && $apId, 500, 'System accounts (AR/AP) are missing.');

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $validated['opening_currency_id'],
                    'rate' => (float) $validated['rate'],
                    'date' => now(),
                    'reference_type' => Ledger::class,
                    'reference_id' => $ledger->id,
                    'remark' => 'Opening balance for supplier ' . $ledger->name,
                ],
                lines: [
                ['account_id' => $equityId, 'ledger_id' => $ledger->id, 'debit' => (float) $validated['amount'], 'credit' => 0, 'remark' => 'Opening balance for customer ' . $ledger->name],
                ['account_id' => $apId, 'debit' => 0, 'credit' => (float) $validated['amount'], 'remark' => 'Opening balance for customer ' . $ledger->name],
            ]);
            $transaction->opening()->create([
                'ledgerable_id' => $ledger->id,
                'ledgerable_type' => 'ledger',
            ]);
        }
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
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
            'opening',
            'opening.transaction.currency',
            'opening.transaction.lines',
            'transactionLines.transaction',
            'transactionLines.transaction.currency',
        ]);

        $purchases = $supplier->purchases->load('transaction.currency');
        $receipts = $supplier->receipts->load('receiveTransaction.currency');
        $payments = $supplier->payments->load('bankTransaction.currency');
        if ($request->expectsJson()) {
            return response()->json([
                'supplier' => new LedgerResource($supplier),
                'purchases' => PurchaseResource::collection($purchases),
                'receipts' => ReceiptResource::collection($receipts),
                'payments' => PaymentResource::collection($payments),
            ]);
        }

        return inertia('Ledgers/Suppliers/Show', [
            'supplier' => new LedgerResource($supplier),
            'purchases' => PurchaseResource::collection($purchases),
            'receipts' => ReceiptResource::collection($receipts),
            'payments' => PaymentResource::collection($payments),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Ledger $supplier)
    {
        $supplier->load(['currency', 'opening', 'opening.transaction.currency','opening.transaction.lines']);
        return inertia('Ledgers/Suppliers/Edit', [
            'supplier' => new LedgerResource($supplier),
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

        if($supplier->opening) {
            TransactionLine::where('transaction_id',$supplier->opening->transaction_id)->forceDelete();
            $supplier->opening->forceDelete();
            $supplier->opening->transaction()->forceDelete();
        }


        if ($validated['amount'] && $validated['amount'] > 0 && $validated['opening_currency_id'] && $validated['rate']) {  // Update existing opening balances
            $glAccounts = Cache::get('gl_accounts');
            $equityId = $glAccounts['opening-balance-equity'];
            $apId = $glAccounts['accounts-payable'];
            $transactionService = app(TransactionService::class);
            abort_unless($equityId && $apId, 500, 'System accounts (AR/AP) are missing.');

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $validated['opening_currency_id'],
                    'rate' => (float) $validated['rate'],
                    'date' => now(),
                    'reference_type' => Ledger::class,
                    'reference_id' => $supplier->id,
                    'remark' => 'Opening balance for supplier ' . $supplier->name,
                ],
                lines: [
                ['account_id' => $equityId, 'debit' => (float) $validated['amount'], 'credit' => 0, 'remark' => 'Opening balance for supplier ' . $supplier->name],
                ['account_id' => $apId, 'debit' => 0, 'ledger_id' => $supplier->id, 'credit' => (float) $validated['amount'], 'remark' => 'Opening balance for supplier ' . $supplier->name],
            ]);

            $transaction->opening()->create([
                'ledgerable_id' => $supplier->id,
                'ledgerable_type' => 'ledger',
            ]);
        }
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        return to_route('suppliers.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.supplier')]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Ledger $supplier)
    {

        $openingTransactionId = $supplier->opening?->transaction_id;

        // Allow delete only when customer has no transactions OR only opening transaction.
        $hasNonOpeningTransactions = TransactionLine::query()
            ->where('ledger_id', $supplier->id)
            ->when(
                $openingTransactionId,
                fn ($q) => $q->where('transaction_id', '!=', $openingTransactionId),
                fn ($q) => $q // no opening found -> any transaction means blocked
            )
            ->exists();

        if ($hasNonOpeningTransactions) {
            return back()->with('error', __('Cannot delete customer: this customer has transactions. Please remove related transactions first.'));
        }

        DB::transaction(function () use ($supplier, $openingTransactionId) {
            if ($openingTransactionId) {
                // Delete the whole opening transaction (both lines) and the opening record.
                TransactionLine::where('transaction_id', $openingTransactionId)->delete();
                Transaction::where('id', $openingTransactionId)->delete();
                $supplier->opening()->delete();
            }

            $supplier->delete();
        });
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        return redirect()->route('suppliers.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.supplier')]));
    }
    public function restore(Request $request, Ledger $supplier)
    {
        $opening = $supplier->opening()->withTrashed()->first();
        $openingTransactionId = $opening?->transaction_id;

        DB::transaction(function () use ($supplier, $openingTransactionId) {
            if ($openingTransactionId) {
                Transaction::withTrashed()->where('id', $openingTransactionId)->restore();
                TransactionLine::withTrashed()->where('transaction_id', $openingTransactionId)->restore();
                $supplier->opening()->withTrashed()->restore();
            }

            $supplier->restore();
        });
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));
        return redirect()->route('suppliers.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.supplier')]));
    }
}

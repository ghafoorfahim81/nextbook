<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\LedgerStoreRequest;
use App\Http\Requests\Ledger\LedgerUpdateRequest;
use App\Http\Resources\Ledger\LedgerResource;
use App\Http\Resources\Transaction\TransactionResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Administration\BranchResource;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use App\Models\Administration\Currency;
use App\Models\Administration\Branch;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
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

        if ($openings->isNotEmpty()) {
            $arId = Account::where('name', 'Account Receivable')->value('id');
            $apId = Account::where('name', 'Account Payable')->value('id');

            abort_unless($arId && $apId, 500, 'System accounts (AR/AP) are missing.');

            $openings->each(function ($opening) use ($ledger, $arId, $apId) {
                $type = $opening['type'] ?? 'debit';
                $accountId = $type === 'credit' ? $arId : $apId;

                $transaction = $ledger->transactions()->create([
                    'amount' => (float) $opening['amount'],
                    'account_id' => $accountId,
                    'currency_id' => $opening['currency_id'],
                    'transactionable_type' => Ledger::class,
                    'transactionable_id' => $ledger->id,
                    'rate' => (float) $opening['rate'],
                    'date' => now(),
                    'type' => $type,
                    'remark' => 'Opening balance for supplier',
                    'created_by' => auth()->id(),
                ]);

                $transaction->opening()->create([
                    'ledgerable_id' => $ledger->id,
                    'ledgerable_type' => 'ledger',
                ]);
            });
        }

        return to_route('suppliers.index')->with('success', 'Supplier created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Ledger $supplier)
    {
        $supplier->load([
            'currency',
            'branch',
            'openings.transaction.currency',
            'transactions.account',
            'transactions.currency',
        ]);

        $transactions = $supplier->transactions;

        $purchases = $transactions->where('reference_type', 'purchase')->values();
        $payments = $transactions->where('reference_type', 'payment')->values();

        if ($request->expectsJson()) {
            return response()->json([
                'supplier' => new LedgerResource($supplier),
                'purchases' => TransactionResource::collection($purchases),
                'payments' => TransactionResource::collection($payments),
            ]);
        }

        return inertia('Ledgers/Suppliers/Show', [
            'supplier' => new LedgerResource($supplier),
            'purchases' => TransactionResource::collection($purchases),
            'payments' => TransactionResource::collection($payments),
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
        $supplier->transactions()->whereHas('opening')->get()->each(function ($transaction) {
            $transaction->opening()->forceDelete();
            $transaction->forceDelete();
        });

        $openings = collect($request->input('openings', []))
            ->filter(function ($opening) {
                return !empty($opening['currency_id']) && (float)($opening['amount'] ?? 0) > 0;
            });

        if ($openings->isNotEmpty()) {  // Update existing opening balances
            $arId = Account::where('name', 'Account Receivable')->value('id');
            $apId = Account::where('name', 'Account Payable')->value('id');

            abort_unless($arId && $apId, 500, 'System accounts (AR/AP) are missing.');

            $openings->each(function ($opening) use ($supplier, $arId, $apId) {
                $type = $opening['type'] ?? 'debit';
                $accountId = $type === 'credit' ? $arId : $apId;

                $transaction = $supplier->transactions()->create([
                    'amount' => (float) $opening['amount'],
                    'account_id' => $accountId,
                    'currency_id' => $opening['currency_id'],
                    'transactionable_type' => Ledger::class,
                    'transactionable_id' => $supplier->id,
                    'rate' => (float) $opening['rate'],
                    'date' => now(),
                    'type' => $type,
                    'remark' => 'Opening balance for supplier',
                    'created_by' => auth()->id(),
                ]);

                $transaction->opening()->create([
                    'ledgerable_id' => $supplier->id,
                    'ledgerable_type' => 'ledger',
                ]);
            });
        }

        return to_route('suppliers.index')->with('success', 'Supplier updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Ledger $supplier)
    {
        $supplier->transactions()->whereHas('opening')->get()->each(function ($transaction) {
            $transaction->opening()->forceDelete();
            $transaction->forceDelete();
        });
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted successfully.');
    }
    public function restore(Request $request, Ledger $supplier)
    {
        $supplier->transactions()->whereHas('opening')->get()->each(function ($transaction) {
            $transaction->opening()->restore();
            $transaction->restore();
        });
        $supplier->restore();
        return redirect()->route('suppliers.index')->with('success', 'Supplier restored successfully.');
    }
}

<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\LedgerStoreRequest;
use App\Http\Resources\Ledger\LedgerResource;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
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
        return inertia('Ledgers/Suppliers/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LedgerStoreRequest $request)
    {
        $validated = $request->validated();
        $validated['type'] = 'supplier';
        $ledger = Ledger::create($validated);
        if ($request->filled('opening_amount') && $request->opening_amount > 0) {
            // Prefer stable identifiers (code/slug) over name to find system accounts
            $arId = Account::where('name', 'Account Receivable')->value('id'); // or ->where('code','AR')
            $apId = Account::where('name', 'Account Payable')->value('id');    // or ->where('code','AP')

            abort_unless($arId && $apId, 500, 'System accounts (AR/AP) are missing.');

            // For customers: debit => AR (asset), credit => AP (liability)
            $accountId = $request->transaction_type === 'debit' ? $arId : $apId;

            $transaction = $ledger->transactions()->create([
                'account_id'   => $accountId,
                'amount'       => (float) $request->opening_amount,
                'transactionable_type' => Ledger::class,
                'currency_id'  => $request->opening_currency_id,   // ensure this exists/validated
                'rate'         => (float) ($request->rate ?? 1),
                'date'         => $request->date ?? now(),
                'type'         => $request->transaction_type ?? 'debit',
                'remark'       => 'Opening balance for customer',
                'created_by'   => auth()->id(),
            ]);

            $transaction->opening()->create([
                'ledgerable_id'   => $ledger->id,
                'ledgerable_type' => \App\Models\Ledger\Ledger::class,
                'created_by'      => auth()->id(),
            ]);
        }

        return to_route('suppliers.index')->with('success', 'Customer created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\LedgerStoreRequest;
use App\Http\Resources\Ledger\LedgerResource;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
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
        return inertia('Ledgers/Customers/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LedgerStoreRequest $request)
    {
        $validated = $request->validated();
        $validated['type'] = 'customer';
        $ledger = Ledger::create($validated);
        if ($request->has('opening_amount') && $request->opening_amount > 0) {
            $transaction = $ledger->transactions()->create([
                'amount' => $request->opening_amount,
                'currency_id' => $request->currency_id,
                'transactionable_type' => Ledger::class,
                'transactionable_id' => $ledger->id,
                'rate' => 1,
                'date' => now(),
                'type' => $request->transaction_type ?? 'debit',
                'remark' => 'Opening balance for customer',
                'created_by' => auth()->id(),
            ]);
            $transaction->opening()->create([
                'ledgerable_id' => $ledger->id,
                'ledgerable_type' => Ledger::class,
            ]);
        }
        return to_route('customers.index')->with('success', 'Customer created successfully.');

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

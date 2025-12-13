<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\LedgerStoreRequest;
use App\Http\Resources\Ledger\LedgerResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Administration\BranchResource;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use App\Models\Administration\Currency;
use App\Models\Administration\Branch;
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
        return inertia('Ledgers/Customers/Create', [
            'currencies' => CurrencyResource::collection(Currency::orderBy('name')->get()),
            'branches' => BranchResource::collection(Branch::orderBy('name')->get()),
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
        if ($request->has('opening_amount') && $request->opening_amount > 0) {
            $account_id = $request->transaction_type == 'credit' ? Account::where('name','Account Receivable')->first()->id : Account::where('name','Account Payable')->first()->id;
            $transaction = $ledger->transactions()->create([
                'amount' => $request->opening_amount,
                'account_id' => $account_id,
                'currency_id' => $request->opening_currency_id,
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
                'ledgerable_type' => 'ledger',
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
    public function edit(Request $request, Ledger $customer)
    {
        return inertia('Ledgers/Customers/Edit', [
            'customer' => new LedgerResource($customer), 
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $ledger = Ledger::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string'],
            'phone_no' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'branch_id' => ['nullable', 'string', 'exists:branches,id'],
            'opening_amount' => ['nullable', 'numeric'],
            'opening_currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'transaction_type' => ['nullable', 'string', 'in:Credit,Debit'],
        ]);

        $ledger->update($validated);

        // Handle opening balance
        $existingOpening = $ledger->transactions()->whereHas('opening')->first();

        if ($request->has('opening_amount') && $request->opening_amount > 0) {
            $account_id = $request->transaction_type == 'credit'
                ? Account::where('name','Account Receivable')->first()->id
                : Account::where('name','Account Payable')->first()->id;

            if ($existingOpening) {
                // Update existing opening transaction
                $existingOpening->update([
                    'amount' => $request->opening_amount,
                    'account_id' => $account_id,
                    'currency_id' => $request->opening_currency_id,
                    'type' => $request->transaction_type ?? 'debit',
                ]);
            } else {
                // Create new opening transaction
                $transaction = $ledger->transactions()->create([
                    'amount' => $request->opening_amount,
                    'account_id' => $account_id,
                    'currency_id' => $request->opening_currency_id,
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
                    'ledgerable_type' => 'ledger',
                ]);
            }
        } elseif ($existingOpening) {
            // Remove opening balance if amount is 0 or not provided
            $existingOpening->opening()->delete();
            $existingOpening->delete();
        }

        return to_route('customers.index')->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $ledger = Ledger::findOrFail($id);
        $ledger->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }
    public function restore(Request $request, Ledger $customer)
    {
        $customer->restore();
        return redirect()->route('customers.index')->with('success', 'Customer restored successfully.');
    }
}

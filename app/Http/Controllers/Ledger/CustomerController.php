<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\LedgerStoreRequest;
use App\Http\Requests\Ledger\LedgerUpdateRequest;
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
                    'remark' => 'Opening balance for customer',
                    'created_by' => auth()->id(),
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
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Ledger $customer)
    {
        $customer->load(['openings.transaction.currency']);
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
        $customer->transactions()->whereHas('opening')->get()->each(function ($transaction) {
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

            $openings->each(function ($opening) use ($customer, $arId, $apId) {
                $type = $opening['type'] ?? 'debit';
                $accountId = $type === 'credit' ? $arId : $apId;

                $transaction = $customer->transactions()->create([
                    'amount' => (float) $opening['amount'],
                    'account_id' => $accountId,
                    'currency_id' => $opening['currency_id'],
                    'transactionable_type' => Ledger::class,
                    'transactionable_id' => $customer->id,
                    'rate' => (float) $opening['rate'],
                    'date' => now(),
                    'type' => $type,
                    'remark' => 'Opening balance for customer',
                    'created_by' => auth()->id(),
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

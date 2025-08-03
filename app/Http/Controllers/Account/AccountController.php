<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AccountStoreRequest;
use App\Http\Requests\Account\AccountUpdateRequest;
use App\Http\Resources\Account\AccountCollection;
use App\Http\Resources\Account\AccountResource;
use App\Models\Account\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'asc');

        $accounts = Account::search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Accounts/Accounts/Index', [
            'items' => AccountResource::collection($accounts),
        ]);
    }

    public function create()
    {
        return inertia('Accounts/Accounts/Create');
    }

    public function store(AccountStoreRequest $request): Response
    {
//        dd('Store method called');
        $validated = $request->validated();

        // Create Account
        $account = Account::create($validated);

        // Optionally create transaction + opening if opening amount exists
        if ($request->has('opening_amount') && $request->opening_amount > 0) {
            $transaction = $account->transactions()->create([
                'amount' => $request->opening_amount,
                'currency_id' => $request->currency_id,
                'transactionable_type' => Account::class,
                'transactionable_id' => $account->id,
                'rate' => 1,
                'date' => now(),
                'type' => $request->transaction_type ?? 'debit',
                'remark' => 'Opening balance for account',
                'created_by' => auth()->id(),
            ]);

            $transaction->opening()->create([
                'ledger_id' => $account->id,
                'ledger_type' => Account::class,
            ]);
        }

        return to_route('accounts.index')->with('success', 'Account created successfully.');
    }


    public function show(Request $request, Account $account): Response
    {
        return new AccountResource($account);
    }

    public function update(AccountUpdateRequest $request, Account $account): Response
    {
        $account->update($request->validated());

        return new AccountResource($account);
    }

    public function destroy(Request $request, Account $account): Response
    {
        $account->delete();

        return response()->noContent();
    }
}

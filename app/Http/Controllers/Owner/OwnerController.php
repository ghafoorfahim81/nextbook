<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\OwnerStoreRequest;
use App\Http\Requests\Owner\OwnerUpdateRequest;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Owner\OwnerResource;
use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Administration\Currency;
use App\Models\Owner\Owner;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Response;
use App\Models\Transaction\Transaction;
class OwnerController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Owner::class, 'owner');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $owners = Owner::query()
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Owners/Owners/Index', [
            'owners' => OwnerResource::collection($owners),
            'currencies' => CurrencyResource::collection(Currency::orderBy('name')->get()),
        ]);
    }

    public function create(Request $request): Response
    {
        return inertia('Owners/Owners/Create');
    }

    public function store(OwnerStoreRequest $request, TransactionService $transactionService)
    {
        $validated = $request->validated();
        DB::transaction(function () use ($validated, $transactionService) {
            $owner = Owner::create([
                'name' => $validated['name'],
                'father_name' => $validated['father_name'],
                'nic' => $validated['nic'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'ownership_percentage' => $validated['ownership_percentage'] ?? 100,
                'is_active' => $validated['is_active'] ?? true,
                'capital_account_id' => $validated['capital_account_id'],
                'drawing_account_id' => $validated['drawing_account_id'],
            ]);

            // Create financial transactions
            $amount = (float) $validated['amount'];
            $currencyId = $validated['currency_id'];
            $rate = (float) $validated['rate'];
            $today = now()->toDateString();

            // Credit owner's capital account (capital contribution)
            $capitalTx = $transactionService->createTransaction([
                'account_id' => $validated['capital_account_id'],
                'amount' => $amount,
                'currency_id' => $currencyId,
                'rate' => $rate,
                'date' => $today,
                'type' => 'credit',
                'remark' => "Capital contribution by {$owner->name}",
                'reference_type' => Owner::class,
                'reference_id' => $owner->id,
            ]);

            // Debit cash-in-hand (money received)
            $cashAccountId = Account::where('slug', 'cash')->value('id');
            $accountTx = $transactionService->createTransaction([
                'account_id' => $validated['account_id'],
                'amount' => $amount,
                'currency_id' => $currencyId,
                'rate' => $rate,
                'date' => $today,
                'type' => 'debit',
                'remark' => "Cash received for {$owner->name} capital",
                'reference_type' => Owner::class,
                'reference_id' => $owner->id,
            ]);

            $owner->update([
                'capital_transaction_id' => $capitalTx->id,
                'account_transaction_id' => $accountTx->id,
            ]);
        });

        if ($request->boolean('create_and_new')) {
            return redirect()->route('owners.create')->with('success', 'Owner created successfully.');
        }
        return redirect()->route('owners.index')->with('success', 'Owner created successfully.');
    }

    public function show(Request $request, Owner $owner)
    {
        $owner->load([
            'capitalAccount',
            'drawingAccount',
            'capitalTransaction.currency',
            'capitalTransaction.account',
            'accountTransaction.currency',
            'accountTransaction.account'
        ]);
        return response()->json([
            'data' => new OwnerResource($owner),
        ]);
    }

    public function edit(Request $request, Owner $owner): Response
    {
        $owner->load(['accountTransaction.account', 'accountTransaction.currency']);
        return inertia('Owners/Owners/Edit', [
            'owner' => new OwnerResource($owner),
        ]);
    }

    public function update(OwnerUpdateRequest $request, Owner $owner)
    {
        // Use similar logic as the store method, but for updating

        $validated = $request->validated();

        DB::transaction(function () use ($owner, $validated) {
            $amount = $validated['amount'] ?? 0;
            $currencyId = $validated['currency_id'];
            $rate = $validated['rate'];

            // Update owner fields
            $owner->update($validated);

            // Update capital transaction
            if ($owner->capital_transaction_id) {
                Transaction::where('id', $owner->capital_transaction_id)->update([
                    'account_id' => $validated['capital_account_id'],
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => now(),
                    'type' => 'credit',
                    'remark' => "Capital contribution by {$owner->name}",
                    'reference_type' => Owner::class,
                    'reference_id' => $owner->id,
                ]);
            }
            // Update account transaction (cash etc)
            if ($owner->account_transaction_id) {
                Transaction::where('id', $owner->account_transaction_id)->update([
                    'account_id' => $validated['account_id'],
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'date' => now(),
                    'type' => 'debit',
                    'remark' => "Cash received for {$owner->name} capital",
                    'reference_type' => Owner::class,
                    'reference_id' => $owner->id,
                ]);
            }
        });
        return redirect()->route('owners.index')->with('success', 'Owner updated successfully.');
    }

    public function destroy(Request $request, Owner $owner)
    {
        $owner->load(['capitalTransaction', 'accountTransaction']);
        $owner->capitalTransaction->delete();
        $owner->accountTransaction->delete();
        $owner->delete();
        return redirect()->route('owners.index')->with('success', 'Owner deleted successfully.');
    }

    public function restore(Request $request, Owner $owner)
    {
        $owner->restore();
        if ($owner->capital_transaction_id) {
            Transaction::where('id', $owner->capital_transaction_id)->restore();
        }
        if ($owner->account_transaction_id) {
            Transaction::where('id', $owner->account_transaction_id)->restore();
        }
        return redirect()->route('owners.index')->with('success', 'Owner restored successfully.');
    }
}



<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AccountStoreRequest;
use App\Http\Requests\Account\AccountUpdateRequest;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Account\AccountTypeResource;
use App\Http\Resources\Administration\BranchResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Ledger\LedgerOpeningResource;
use App\Http\Resources\Transaction\TransactionResource;
use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Administration\Branch;
use App\Models\Administration\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Account::class, 'chart_of_account');
    }

    public function index(Request $request)
    {

        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');

        $accounts = Account::with('transactions')
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Accounts/Accounts/Index', [
            'accounts' => AccountResource::collection($accounts),
        ]);
    }

    public function create()
    {
        return inertia('Accounts/Accounts/Create', [
            'currencies' => CurrencyResource::collection(Currency::orderBy('name')->get()),
            'branches' => BranchResource::collection(Branch::orderBy('name')->get()),
            'accountTypes' => AccountTypeResource::collection(AccountType::orderBy('name')->get()),
        ]);
    }

    public function store(AccountStoreRequest $request)
    {
        $validated = $request->validated();
        $validated['slug'] = Str::slug($validated['name']);
        $account = Account::create($validated);

        $openings = collect($request->input('openings', []))
            ->filter(function ($opening) {
                return !empty($opening['currency_id']) && (float) ($opening['amount'] ?? 0) > 0;
            });

        if ($openings->isNotEmpty()) {
            $openings->each(function ($opening) use ($account) {
                $type = $opening['type'] ?? 'debit';

                $transaction = $account->transactions()->create([
                    'amount' => (float) $opening['amount'],
                    'currency_id' => $opening['currency_id'],
                    'rate' => (float) ($opening['rate'] ?? 1),
                    'date' => now(),
                    'type' => $type,
                    'remark' => 'Opening balance for account',
                    'created_by' => Auth::id(),
                ]);

                $transaction->opening()->create([
                    'ledgerable_id' => $account->id,
                    'ledgerable_type' => 'account',
                ]);
            });
        }

        return to_route('chart-of-accounts.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.account')]));
    }


    public function show(Request $request, Account $chart_of_account)
    {
        $chart_of_account->load([
            'accountType',
            'transactions.currency',
            'openings.transaction.currency',
        ]);

        $transactions = $chart_of_account->transactions;
        $openings = $chart_of_account->openings;

        if ($request->expectsJson()) {
            return response()->json([
                'account' => new AccountResource($chart_of_account),
                'transactions' => TransactionResource::collection($chart_of_account->transactions),
                'openings' => LedgerOpeningResource::collection($chart_of_account->openings),
            ]);
        }

        return inertia('Accounts/Accounts/Show', [
            'account' => new AccountResource($chart_of_account),
            'transactions' => TransactionResource::collection($transactions),
            'openings' => LedgerOpeningResource::collection($openings),
        ]);
    }

    public function edit(Request $request, Account $chart_of_account)
    {
        $chart_of_account->load(['accountType', 'transactions.currency', 'openings.transaction.currency']);

        return inertia('Accounts/Accounts/Edit', [
            'account' => new AccountResource($chart_of_account),
        ]);
    }

    public function update(AccountUpdateRequest $request, Account $chart_of_account)
    {
        $validated = $request->validated();
        $validated['slug'] = Str::slug($validated['name']);
        $chart_of_account->update($validated);

        // Remove existing opening balances for this account
        $chart_of_account->transactions()->whereHas('opening')->get()->each(function ($transaction) {
            $transaction->opening()->forceDelete();
            $transaction->forceDelete();
        });

        $openings = collect($request->input('openings', []))
            ->filter(function ($opening) {
                return !empty($opening['currency_id']) && (float) ($opening['amount'] ?? 0) > 0;
            });

        if ($openings->isNotEmpty()) {
            $openings->each(function ($opening) use ($chart_of_account) {
                $type = $opening['type'] ?? 'debit';

                $transaction = $chart_of_account->transactions()->create([
                    'amount' => (float) $opening['amount'],
                    'currency_id' => $opening['currency_id'],
                    'rate' => (float) ($opening['rate'] ?? 1),
                    'date' => now(),
                    'type' => $type,
                    'remark' => 'Opening balance for account',
                    'created_by' => Auth::id(),
                ]);

                $transaction->opening()->create([
                    'ledgerable_id' => $chart_of_account->id,
                    'ledgerable_type' => 'account',
                ]);
            });
        }

        return to_route('chart-of-accounts.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.account')]));
    }

    public function destroy(Request $request, Account $chart_of_account)
    {
        if (!$chart_of_account->canBeDeleted()) {
            $message = $chart_of_account->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
            return redirect()->route('chart-of-accounts.index')->with('error', $message);
        }
        if($chart_of_account->is_main) {
            return redirect()->route('chart-of-accounts.index')->with('error', __('general.cannot_delete_main_account'));
        }

        $chart_of_account->transactions()->whereHas('opening')->get()->each(function ($transaction) {
            $transaction->opening()->delete();
            $transaction->delete();
        });
        $chart_of_account->delete();

        return redirect()->route('chart-of-accounts.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.account')]));
    }
    public function restore(Request $request, Account $chart_of_account)
    {
        $chart_of_account->transactions()->whereHas('opening')->get()->each(function ($transaction) {
            $transaction->opening()->restore();
            $transaction->restore();
        });
        $chart_of_account->restore();
        return redirect()->route('chart-of-accounts.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.account')]));
    }
}

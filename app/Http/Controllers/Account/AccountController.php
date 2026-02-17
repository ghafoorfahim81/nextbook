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
use App\Services\TransactionService;
use Illuminate\Support\Facades\Cache;
use App\Models\Transaction\TransactionLine;
use App\Models\Transaction\Transaction;
use App\Models\User;
class AccountController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Account::class, 'chart_of_account');
    }

    public function index(Request $request)
    {

        $perPage = $request->input('perPage',  recordsPerPage());
        $sortField = $request->input('sortField', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $accounts = Account::with(['accountType'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Accounts/Accounts/Index', [
            'accounts' => AccountResource::collection($accounts),
            'filterOptions' => [
                'accountTypes' => AccountType::orderBy('name')->get(['id', 'name']),
                'users' => User::query()
                    ->whereNull('deleted_at')
                    ->orderBy('name')
                    ->get(['id', 'name']),
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

            $glAccounts = Cache::get('gl_accounts');
            $transactionService = app(TransactionService::class);
        if ($validated['amount'] && $validated['amount'] > 0) {
            // $nature = $account->accountType->nature ?? 'asset';
            // // Map debit/credit per account nature (see image)
            // $mappings = [
            //     'asset'    => ['debit' => $account->id, 'credit' => $glAccounts['opening-balance-equity']],
            //     'liability'=> ['debit' => $glAccounts['opening-balance-equity'], 'credit' => $account->id],
            //     'equity'   => ['debit' => $glAccounts['opening-balance-equity'], 'credit' => $account->id],
            //     'income'   => ['debit' => $glAccounts['opening-balance-equity'], 'credit' => $account->id],
            //     'expense'  => ['debit' => $account->id, 'credit' => $glAccounts['opening-balance-equity']],
            // ];
            // $map = $mappings[$nature] ?? $mappings['asset'];
            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $validated['currency_id'],
                    'rate' => (float) ($validated['rate'] ?? 1),
                    'date' => now(),
                    'reference_type' => Account::class,
                    'reference_id' => $account->id,
                    'remark' => 'Opening balance for account ' . $account->name,
                ],
                lines: [
                    ['account_id' => $account->id, 'debit' => (float) $validated['amount'], 'credit' => 0, 'remark' => 'Opening balance for account ' . $account->name],
                    ['account_id' => $glAccounts['opening-balance-equity'], 'debit' => 0, 'credit' => (float) $validated['amount'], 'remark' => 'Opening balance for account ' . $account->name],
                ],
            );
            $account->opening()->create(['transaction_id' => $transaction->id]);
        }
        if ($request->boolean('stay') || $request->boolean('create_and_new')) {
            return redirect()->route('chart-of-accounts.create')->with('success', __('general.created_successfully', ['resource' => __('general.resource.account')]));
        }

        return to_route('chart-of-accounts.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.account')]));
    }


    public function show(Request $request, Account $chart_of_account)
    {
        $chart_of_account->load([
            'accountType',
            'branch',
            'opening',
            'opening.transaction.currency',
            'opening.transaction.lines',
            'createdBy',
            'updatedBy',
        ]);

        // Transactions are now represented by Transaction + TransactionLines.
        // Fetch all transactions that include this account in their lines.
        $transactions = Transaction::query()
            ->whereHas('lines', function ($q) use ($chart_of_account) {
                $q->where('account_id', $chart_of_account->id);
            })
            ->with([
                'currency',
                'lines' => function ($q) use ($chart_of_account) {
                    $q->where('account_id', $chart_of_account->id);
                },
            ])
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'account' => new AccountResource($chart_of_account),
                'transactions' => TransactionResource::collection($transactions),
                'opening' => $chart_of_account->opening
                    ? new LedgerOpeningResource($chart_of_account->opening)
                    : null,
            ]);
        }

        return inertia('Accounts/Accounts/Show', [
            'account' => new AccountResource($chart_of_account),
            'transactions' => TransactionResource::collection($transactions),
            'opening' => $chart_of_account->opening
                ? new LedgerOpeningResource($chart_of_account->opening)
                : null,
        ]);
    }

    public function edit(Request $request, Account $chart_of_account)
    {
        $chart_of_account->load(['accountType','opening', 'opening.transaction.currency','opening.transaction.lines']);
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
        if($chart_of_account->opening) {
            $chart_of_account->opening()->forceDelete();
            TransactionLine::where('transaction_id', $chart_of_account->opening->transaction_id)->forceDelete();
            Transaction::where('id', $chart_of_account->opening->transaction_id)->forceDelete();

        }

        if ($validated['amount'] && $validated['amount'] > 0) {
            $glAccounts = Cache::get('gl_accounts');
            $transactionService = app(TransactionService::class);
            // $nature = $chart_of_account->accountType->nature ?? 'asset';
            // Map debit/credit per account nature (see image)
            // $mappings = [
            //     'asset'    => ['debit' => $chart_of_account->id, 'credit' => $glAccounts['opening-balance-equity']],
            //     'liability'=> ['debit' => $chart_of_account->id, 'credit' => $glAccounts['opening-balance-equity']],
            //     'equity'   => ['debit' => $chart_of_account->id, 'credit' => $glAccounts['opening-balance-equity']],
            //     'income'   => ['debit' => $chart_of_account->id, 'credit' => $glAccounts['opening-balance-equity']],
            //     'expense'  => ['debit' => $chart_of_account->id, 'credit' => $glAccounts['opening-balance-equity']],
            // ];
            // $map = $mappings[$nature] ?? $mappings['asset'];
            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $validated['currency_id'],
                    'rate' => (float) ($validated['rate'] ?? 1),
                    'date' => now(),
                    'reference_type' => Account::class,
                    'reference_id' => $chart_of_account->id,
                    'remark' => 'Opening balance for account ' . $chart_of_account->name,
                ],
                lines: [
                    ['account_id' => $chart_of_account->id, 'debit' => (float) $validated['amount'], 'credit' => 0, 'remark' => 'Opening balance for account ' . $chart_of_account->name],
                    ['account_id' => $glAccounts['opening-balance-equity'], 'debit' => 0, 'credit' => (float) $validated['amount'], 'remark' => 'Opening balance for account ' . $chart_of_account->name],
                ],
            );
            $chart_of_account->opening()->create(['transaction_id' => $transaction->id]);
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

        if($chart_of_account->opening) {
            TransactionLine::where('transaction_id', $chart_of_account->opening->transaction_id)->delete();
                Transaction::where('id', $chart_of_account->opening->transaction_id)->delete();
                $chart_of_account->opening()->delete();
            }
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

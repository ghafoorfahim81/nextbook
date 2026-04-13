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
use Illuminate\Support\Facades\DB;
use App\Models\Transaction\TransactionLine;
use App\Models\Transaction\Transaction;
use App\Models\User;
use App\Services\SpreadsheetExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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

        $accounts = Account::with(['accountType', 'parent'])
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
            'parent',
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

    public function exportTransactions(
        Request $request,
        Account $chart_of_account,
        SpreadsheetExportService $spreadsheetExportService,
    ): BinaryFileResponse {
        $this->authorize('view', $chart_of_account);

        $chart_of_account->loadMissing(['accountType', 'branch']);

        $dateConversionService = app(\App\Services\DateConversionService::class);

        $transactions = Transaction::query()
            ->whereHas('lines', function ($query) use ($chart_of_account) {
                $query->where('account_id', $chart_of_account->id);
            })
            ->with([
                'currency',
                'lines' => function ($query) use ($chart_of_account) {
                    $query->where('account_id', $chart_of_account->id);
                },
            ])
            ->orderBy('date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $rows = [];
        $runningBalance = 0.0;

        foreach ($transactions as $transaction) {
            $line = $transaction->lines->first();
            $rate = (float) ($transaction->rate ?: 1);
            $debit = round((float) ($line?->debit ?? 0) * $rate, 2);
            $credit = round((float) ($line?->credit ?? 0) * $rate, 2);
            $runningBalance += $debit - $credit;

            $rows[] = [
                'date' => $dateConversionService->toDisplay($transaction->date) ?: $transaction->date,
                'transaction_number' => $transaction->voucher_number ?: '-',
                'description' => trim((string) ($line?->remark ?? $transaction->remark ?? '')) ?: '-',
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($runningBalance, 2),
                'currency' => $transaction->currency?->code ?? $transaction->currency?->name ?? '',
                'rate' => $rate,
            ];
        }

        $sheetTitle = $spreadsheetExportService->localeTranslation('general', 'transaction_summary', 'Transaction Summary');
        $titlePrefix = $chart_of_account->local_name ?: $chart_of_account->name;

        return $spreadsheetExportService->download([
            'filename' => Str::slug($titlePrefix . '-' . $sheetTitle) . '-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name' => $sheetTitle,
            'sheet_title' => $sheetTitle,
            'title' => $titlePrefix . ' - ' . $sheetTitle,
            'company_name' => $this->exportCompanyName($request),
            'exported_on' => now()->format('Y m d'),
            'rtl' => in_array(app()->getLocale(), ['fa', 'ps'], true),
            'include_row_number' => true,
            'row_number_label' => $spreadsheetExportService->localeTranslation('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'date', 'label' => $spreadsheetExportService->localeTranslation('general', 'date', 'Date'), 'width' => 14],
                ['key' => 'transaction_number', 'label' => $spreadsheetExportService->localeTranslation('general', 'number', 'Number'), 'width' => 16],
                ['key' => 'description', 'label' => $spreadsheetExportService->localeTranslation('general', 'description', 'Description'), 'width' => 34],
                ['key' => 'debit', 'label' => $spreadsheetExportService->localeTranslation('general', 'debit', 'Debit'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'credit', 'label' => $spreadsheetExportService->localeTranslation('general', 'credit', 'Credit'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'balance', 'label' => $spreadsheetExportService->localeTranslation('general', 'balance', 'Balance'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'currency', 'label' => $spreadsheetExportService->localeTranslation('admin', 'currency.currency', 'Currency'), 'width' => 12],
                ['key' => 'rate', 'label' => $spreadsheetExportService->localeTranslation('general', 'rate', 'Rate'), 'type' => 'money', 'align' => 'right', 'width' => 12],
            ],
            'rows' => $rows,
        ]);
    }

    protected function exportCompanyName(Request $request): string
    {
        $company = data_get($request->user(), 'company');

        if (! $company) {
            return config('app.name');
        }

        return match (app()->getLocale()) {
            'fa' => $company->name_fa ?: $company->name_en ?: $company->abbreviation ?: config('app.name'),
            'ps' => $company->name_pa ?: $company->name_en ?: $company->abbreviation ?: config('app.name'),
            default => $company->name_en ?: $company->abbreviation ?: $company->name_fa ?: $company->name_pa ?: config('app.name'),
        };
    }

    public function edit(Request $request, Account $chart_of_account)
    {
        $chart_of_account->load(['accountType','opening', 'opening.transaction.currency','opening.transaction.lines', 'parent']);
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
        if($chart_of_account->is_main) {
            return redirect()->route('chart-of-accounts.index')->with('error', __('general.cannot_delete_main_account'));
        }

        if (!$chart_of_account->canBeDeleted()) {
            $message = $chart_of_account->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
            return redirect()->route('chart-of-accounts.index')->with('error', $message);
        }

        $openingTransactionId = $chart_of_account->opening?->transaction_id;

        DB::transaction(function () use ($chart_of_account, $openingTransactionId) {
            if ($openingTransactionId) {
                TransactionLine::where('transaction_id', $openingTransactionId)->delete();
                Transaction::where('id', $openingTransactionId)->delete();
                $chart_of_account->opening()->delete();
            }

            $chart_of_account->delete();
        });

        return redirect()->route('chart-of-accounts.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.account')]));
    }

    public function restore(Request $request, Account $chart_of_account)
    {
        $opening = $chart_of_account->opening()->withTrashed()->first();
        $openingTransactionId = $opening?->transaction_id;

        DB::transaction(function () use ($chart_of_account, $openingTransactionId) {
            if ($openingTransactionId) {
                Transaction::withTrashed()->where('id', $openingTransactionId)->restore();
                TransactionLine::withTrashed()->where('transaction_id', $openingTransactionId)->restore();
                $chart_of_account->opening()->withTrashed()->restore();
            }

            $chart_of_account->restore();
        });

        return redirect()->route('chart-of-accounts.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.account')]));
    }
}

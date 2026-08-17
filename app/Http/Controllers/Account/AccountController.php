<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AccountStoreRequest;
use App\Http\Requests\Account\AccountUpdateRequest;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Account\AccountTypeResource;
use App\Http\Resources\Account\AccountListResource;
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
use App\Support\BranchContext;
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
            'accounts' => AccountListResource::collection($accounts),
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

            $glAccounts = BranchContext::glAccounts();
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
                    'voucher_number' => 'Account Opening Balance ' . $account->name . ' ' . $account->number,
                    'date' => now(),
                    'reference_type' => Account::class,
                    'reference_id' => $account->id,
                    'remark' => 'Opening balance for account ' . $account->name,
                ],
                lines: [
                    ['account_id' => $account->id, 'debit' => (float) $validated['amount'], 'credit' => 0,
                    'remark' => 'Opening balance',
                    'remark_fa' => 'موجودی اولیه',
                    'remark_ps' => 'د پرانیستلو بیلانس',
                    ],
                    ['account_id' => $glAccounts['opening-balance-equity'], 'debit' => 0, 'credit' => (float) $validated['amount'],
                    'remark' => 'Opening balance for account ' . $account->name,
                    'remark_fa' => 'موجودی اولیه برای حساب ' . $account->local_name,
                    'remark_ps' =>'د'. ' '. $account->local_name.' '.'د پرانیستلو بیلانس ',
                    ],
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

        $currencyBalances = $this->currencyBalances($transactions);
        $accountCurrency = $this->accountCurrency($chart_of_account, $transactions);
        $convertedBalance = $this->convertedBalance($currencyBalances, $accountCurrency);

        if ($request->expectsJson()) {
            return response()->json([
                'account' => new AccountResource($chart_of_account),
                'transactions' => TransactionResource::collection($transactions),
                'currencyBalances' => $currencyBalances,
                'convertedBalance' => $convertedBalance,
                'opening' => $chart_of_account->opening
                    ? new LedgerOpeningResource($chart_of_account->opening)
                    : null,
            ]);
        }

        return inertia('Accounts/Accounts/Show', [
            'account' => new AccountResource($chart_of_account),
            'transactions' => TransactionResource::collection($transactions),
            'currencyBalances' => $currencyBalances,
            'convertedBalance' => $convertedBalance,
            'opening' => $chart_of_account->opening
                ? new LedgerOpeningResource($chart_of_account->opening)
                : null,
            'balanceNatureFormat' => balanceNatureFormat(),
        ]);
    }

    /**
     * The currency an account is understood to be held in.
     *
     * Accounts carry no currency column, so it is inferred: the opening balance
     * decides it, and failing that the earliest transaction posted to the
     * account. Nothing is written anywhere — this only labels the detail view.
     *
     * @param  \Illuminate\Support\Collection<int, Transaction>  $transactions
     */
    protected function accountCurrency(Account $account, $transactions): ?Currency
    {
        $opening = $account->opening?->transaction?->currency;

        if ($opening) {
            return $opening;
        }

        return collect($transactions)
            ->sortBy([['date', 'asc'], ['created_at', 'asc']])
            ->first(fn (Transaction $transaction) => $transaction->currency !== null)
            ?->currency;
    }

    /**
     * The whole account expressed in its own currency.
     *
     * Positions the account holds in other currencies are carried to the account
     * currency through their home-currency equivalent, divided by the account
     * currency's current rate. Balances already in the account currency are
     * taken as they stand, so they never pick up rounding from a round trip.
     *
     * This is a reading of the balance, not a posting: no line is converted or
     * rewritten, and the figure moves whenever the exchange rate does.
     *
     * @param  array<int, array<string, mixed>>  $currencyBalances
     * @return array<string, mixed>|null
     */
    protected function convertedBalance(array $currencyBalances, ?Currency $accountCurrency): ?array
    {
        $rate = (float) ($accountCurrency?->exchange_rate ?? 0);

        if (! $accountCurrency || $rate <= 0 || $currencyBalances === []) {
            return null;
        }

        $net = 0.0;

        foreach ($currencyBalances as $row) {
            $net += $row['currency_id'] === $accountCurrency->id
                ? (float) $row['net_balance']
                : (float) $row['home_equivalent'] / $rate;
        }

        $net = round($net, 2);

        return [
            'currency_id' => $accountCurrency->id,
            'currency_code' => $accountCurrency->code ?: $accountCurrency->name,
            'currency_name' => $accountCurrency->name,
            'rate' => $rate,
            'amount' => abs($net),
            'net_balance' => $net,
            'balance_nature' => $net >= 0 ? 'dr' : 'cr',
        ];
    }

    /**
     * Native totals per currency for the account detail view.
     *
     * A cash or bank account holds real money in the currency it was opened in,
     * so converting its lines to the home currency here misstates what is
     * actually in the drawer. Every currency keeps its own debit, credit and
     * balance; the home-currency equivalent is carried alongside as a
     * cross-currency footnote, not as the headline figure.
     *
     * Built from the same transactions the detail table renders so the card and
     * the table can never disagree.
     *
     * @param  \Illuminate\Support\Collection<int, Transaction>  $transactions
     * @return array<int, array<string, mixed>>
     */
    protected function currencyBalances($transactions): array
    {
        return collect($transactions)
            ->flatMap(fn (Transaction $transaction) => $transaction->lines->map(fn ($line) => [
                'currency' => $transaction->currency,
                'rate' => (float) ($transaction->rate ?: 1),
                'debit' => (float) ($line->debit ?? 0),
                'credit' => (float) ($line->credit ?? 0),
            ]))
            ->groupBy(fn (array $row) => $row['currency']?->id ?? '')
            ->map(function ($rows, $currencyId) {
                $currency = $rows->first()['currency'];
                $debit = round((float) $rows->sum('debit'), 2);
                $credit = round((float) $rows->sum('credit'), 2);
                $net = round($debit - $credit, 2);

                return [
                    'currency_id' => $currencyId,
                    'currency_code' => $currency?->code ?: ($currency?->name ?? ''),
                    'currency_name' => $currency?->name ?? '',
                    'currency_symbol' => $currency?->symbol,
                    'is_base_currency' => (bool) ($currency?->is_base_currency ?? false),
                    'total_debit' => $debit,
                    'total_credit' => $credit,
                    'balance' => abs($net),
                    'net_balance' => $net,
                    'balance_nature' => $net >= 0 ? 'dr' : 'cr',
                    'home_equivalent' => round(
                        (float) $rows->sum(fn (array $row) => ($row['debit'] - $row['credit']) * $row['rate']),
                        2
                    ),
                ];
            })
            ->sortBy(fn (array $row) => [$row['is_base_currency'] ? 0 : 1, $row['currency_code']])
            ->values()
            ->all();
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

        foreach ($transactions as $transaction) {
            $rate = (float) ($transaction->rate ?: 1);

            foreach ($transaction->lines as $line) {
                // Amounts stay in the transaction's own currency, matching the
                // account detail table. The currency and rate columns carry the
                // conversion information for anyone who needs it.
                $debit = round((float) ($line?->debit ?? 0), 2);
                $credit = round((float) ($line?->credit ?? 0), 2);

                $rows[] = [
                    'date' => $dateConversionService->toDisplay($transaction->date) ?: $transaction->date,
                    'transaction_number' => $transaction->voucher_number ?: '-',
                    'description' => $this->localisedRemark($line, $transaction),
                    'debit' => $debit,
                    'credit' => $credit,
                    // 'balance' => round($runningBalance, 2),
                    'currency' => $transaction->currency?->code ?? $transaction->currency?->name ?? '',
                    'rate' => $rate,
                ];
            }
        }

        $sheetTitle = $spreadsheetExportService->localeTranslation('general', 'transaction_summary', 'Transaction Summary');
        $accountName = match (app()->getLocale()) {
            'fa', 'ps' => $chart_of_account->local_name ?: $chart_of_account->name,
            default => $chart_of_account->name ?: $chart_of_account->local_name,
        };
        $headerTitle = trim($accountName) !== '' ? $sheetTitle . ' - ' . $accountName : $sheetTitle;

        return $spreadsheetExportService->download([
            'filename' => Str::slug($chart_of_account->name . '-' . $sheetTitle) . '-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name' => $sheetTitle,
            'sheet_title' => $headerTitle,
            'title' => $headerTitle,
            'company_name' => $this->exportCompanyName($request),
            'exported_on' => now()->format('Y m d'),
            'rtl' => in_array(app()->getLocale(), ['fa', 'ps'], true),
            'include_row_number' => true,
            'row_number_label' => $spreadsheetExportService->localeTranslation('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'date', 'label' => $spreadsheetExportService->localeTranslation('general', 'date', 'Date'), 'width' => 14],
                // ['key' => 'transaction_number', 'label' => $spreadsheetExportService->localeTranslation('general', 'number', 'Number'), 'width' => 16],
                ['key' => 'description', 'label' => $spreadsheetExportService->localeTranslation('general', 'description', 'Description'), 'width' => 34],
                ['key' => 'currency', 'label' => $spreadsheetExportService->localeTranslation('admin', 'currency.currency', 'Currency'), 'width' => 12],
                ['key' => 'rate', 'label' => $spreadsheetExportService->localeTranslation('general', 'rate', 'Rate'), 'type' => 'money', 'align' => 'right', 'width' => 12],
                ['key' => 'debit', 'label' => $spreadsheetExportService->localeTranslation('general', 'debit', 'Debit'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'credit', 'label' => $spreadsheetExportService->localeTranslation('general', 'credit', 'Credit'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                // ['key' => 'balance', 'label' => $spreadsheetExportService->localeTranslation('general', 'balance', 'Balance'), 'type' => 'money', 'align' => 'right', 'width' => 14],
            ],
            'rows' => $rows,
        ]);
    }

    /**
     * The line remark in the active locale, falling back to the English line
     * remark and then the transaction remark.
     */
    protected function localisedRemark(?TransactionLine $line, Transaction $transaction): string
    {
        $localised = match (app()->getLocale()) {
            'fa' => $line?->remark_fa,
            'ps' => $line?->remark_ps,
            default => null,
        };

        foreach ([$localised, $line?->remark, $transaction->remark] as $remark) {
            if (trim((string) $remark) !== '') {
                return trim((string) $remark);
            }
        }

        return '-';
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
            $glAccounts = BranchContext::glAccounts();
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
                    'voucher_number' => 'Account Opening Balance ' . $chart_of_account->name . ' ' . $chart_of_account->number,
                    'date' => now(),
                    'reference_type' => Account::class,
                    'reference_id' => $chart_of_account->id,
                    'remark' => 'Opening balance for account ' . $chart_of_account->name,
                ],
                lines: [
                    ['account_id' => $chart_of_account->id, 'debit' => (float) $validated['amount'], 'credit' => 0,
                    'remark' => 'Opening balance',
                    'remark_fa' => 'موجودی اولیه',
                    'remark_ps' => 'د پرانیستلو بیلانس',
                    ],
                    ['account_id' => $glAccounts['opening-balance-equity'], 'debit' => 0, 'credit' => (float) $validated['amount'],
                    'remark' => 'Opening balance for account ' . $chart_of_account->name,
                    'remark_fa' => 'موجودی اولیه برای حساب ' . $chart_of_account->local_name,
                    'remark_ps' =>'د'. ' '. $chart_of_account->local_name.' '.'د پرانیستلو بیلانس ',
                    ],
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

    public function forceDelete(Request $request, Account $chart_of_account)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('accounts', (string) $chart_of_account->id);

        return redirect()->route('chart-of-accounts.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.account')]));
    }

    public function exportList(Request $request, SpreadsheetExportService $exporter)
    {
        $this->authorize('viewAny', Account::class);

        $sortField = $request->input('sortField', 'created_at');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $accounts = Account::with(['accountType', 'parent'])
            ->withSum('transactionLines as total_debit', 'debit')
            ->withSum('transactionLines as total_credit', 'credit')
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->get();

        $t = fn (string $group, string $key, string $fallback = '') => $exporter->localeTranslation($group, $key, $fallback);
        $label = $t('account', 'chart_of_accounts', 'Chart of Accounts');

        $rows = $accounts->map(fn ($a) => [
            'number'       => $a->number,
            'name'         => $a->name,
            'local_name'   => $a->local_name ?? '-',
            'account_type' => $a->accountType?->name ?? '-',
            'parent'       => $a->parent?->name ?? '-',
            'balance'      => round((float) ($a->total_debit ?? 0) - (float) ($a->total_credit ?? 0), 2),
        ])->all();

        return $exporter->download([
            'filename'           => 'chart-of-accounts-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name'         => $label,
            'sheet_title'        => $label,
            'title'              => $label,
            'company_name'       => $this->exportCompanyName($request),
            'exported_on'        => now()->format('Y m d'),
            'rtl'                => in_array(app()->getLocale(), ['fa', 'ps'], true),
            'include_row_number' => true,
            'row_number_label'   => $t('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'number',       'label' => $t('general', 'number', 'Number'), 'width' => 10],
                ['key' => 'name',         'label' => $t('general', 'name', 'Name'), 'width' => 22],
                ['key' => 'local_name',   'label' => $t('account', 'local_name', 'Local Name'), 'width' => 22],
                ['key' => 'account_type', 'label' => $t('account', 'account_type', 'Account Type'), 'width' => 18],
                ['key' => 'parent',       'label' => $t('account', 'parent', 'Parent'), 'width' => 22],
                ['key' => 'balance',      'label' => $t('general', 'balance', 'Balance'), 'type' => 'money', 'align' => 'right', 'width' => 16],
            ],
            'rows' => $rows,
        ]);
    }
}

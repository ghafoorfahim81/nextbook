<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\LedgerStoreRequest;
use App\Http\Requests\Ledger\LedgerUpdateRequest;
use App\Http\Resources\Ledger\LedgerResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Administration\BranchResource;
use App\Http\Resources\Sale\SaleResource;
use App\Http\Resources\Receipt\ReceiptResource;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Models\Administration\Currency;
use App\Models\Administration\Branch;
use Illuminate\Http\Request;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Cache;
use App\Models\Transaction\TransactionLine;
use Illuminate\Support\Facades\DB;
use App\Support\Inertia\CacheKey;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Services\SpreadsheetExportService;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
class CustomerController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Ledger::class, 'customer');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $type = $request->input('type', 'customer'); // default to customer

        $customers = Ledger::search($request->query('search'))
            ->where('type', $type) // Filter by type
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Ledgers/Customers/Index', [
            'customers' => LedgerResource::collection($customers),
            'filterOptions' => [
                'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name']),
                'users' => User::query()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
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
        $glAccounts = Cache::get('gl_accounts');
        $transactionService = app(TransactionService::class);
        if ($validated['opening_currency_id'] && $validated['amount'] && $validated['amount'] > 0) {

            $arId = $glAccounts['account-receivable'];
            $equityId = $glAccounts['opening-balance-equity'];

            abort_unless($arId && $equityId, 500, 'System accounts (AR/AP) are missing.');

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $validated['opening_currency_id'],
                    'rate' => (float) $validated['rate'],
                    'date' => Carbon::now()->toDateString(),
                    'reference_type' => Ledger::class,
                    'reference_id' => $ledger->id,
                    'remark' => 'Opening balance for customer ' . $ledger->name,
                ],
                lines: [
                ['account_id' => $arId, 'ledger_id' => $ledger->id, 'debit' => (float) $validated['amount'], 'credit' => 0, 'remark' => 'Opening balance for customer ' . $ledger->name],
                ['account_id' => $equityId, 'debit' => 0, 'credit' => (float) $validated['amount'], 'remark' => 'Opening balance for customer ' . $ledger->name],
            ]);
            $transaction->opening()->create([
                'ledgerable_id' => $ledger->id,
                'ledgerable_type' => 'ledger',
            ]);
        }
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));


        if ($request->boolean('stay') || $request->boolean('create_and_new')) {
            return to_route('customers.create')
                ->with('success', __('general.created_successfully', ['resource' => __('general.resource.customer')]));
        }

        return to_route('customers.index')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.customer')]));

    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Ledger $customer)
    {
        $customer->load([
            'currency',
            'opening',
            'opening.transaction.currency',
            'opening.transaction.lines',
            'transactionLines.transaction',
            'transactionLines.transaction.currency',
        ]);

        $sales = $customer->sales->load('transaction.currency');
        $receipts = $customer->receipts->load('transaction.currency');
        $payments = $customer->payments->load('transaction.currency');
        if ($request->expectsJson()) {
            return response()->json([
                'customer' => new LedgerResource($customer),
                'sales' => SaleResource::collection($sales),
                'receipts' => ReceiptResource::collection($receipts),
                'payments' => PaymentResource::collection($payments),
            ]);
        }

        return inertia('Ledgers/Customers/Show', [
            'customer' => new LedgerResource($customer),
            'sales' => SaleResource::collection($sales),
            'receipts' => ReceiptResource::collection($receipts),
            'payments' => PaymentResource::collection($payments),
        ]);
    }

    public function export(
        Request $request,
        Ledger $customer,
        SpreadsheetExportService $spreadsheetExportService,
    ): BinaryFileResponse {
        $this->authorize('view', $customer);

        $validated = $request->validate([
            'list' => ['nullable', 'string', Rule::in(['sales', 'receipts', 'payments'])],
        ]);

        $list = $validated['list'] ?? 'sales';
        $customer->loadMissing(['currency', 'branch']);

        $rows = match ($list) {
            'receipts' => $this->exportReceiptRows($customer),
            'payments' => $this->exportPaymentRows($customer),
            default => $this->exportSaleRows($customer),
        };

        $moduleLabel = match ($list) {
            'receipts' => $spreadsheetExportService->localeTranslation('receipt', 'receipts', 'Receipts'),
            'payments' => $spreadsheetExportService->localeTranslation('payment', 'payments', 'Payments'),
            default => $spreadsheetExportService->localeTranslation('sale', 'sales', 'Sales'),
        };

        $entityLabel = $spreadsheetExportService->localeTranslation('ledger', 'customer', 'Customer');
        $sheetTitle = $entityLabel . ' ' . $moduleLabel;

        return $spreadsheetExportService->download([
            'filename' => Str::slug($customer->name . '-' . $sheetTitle) . '-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name' => $sheetTitle,
            'sheet_title' => $sheetTitle,
            'title' => $customer->name . ' - ' . $moduleLabel,
            'company_name' => $this->exportCompanyName($request),
            'exported_on' => now()->format('Y m d'),
            'rtl' => in_array(app()->getLocale(), ['fa', 'ps'], true),
            'include_row_number' => true,
            'row_number_label' => $spreadsheetExportService->localeTranslation('report', 'columns.no', 'No.'),
            'columns' => match ($list) {
                'receipts', 'payments' => [
                    ['key' => 'number', 'label' => $spreadsheetExportService->localeTranslation('general', 'number', 'Number')],
                    ['key' => 'date', 'label' => $spreadsheetExportService->localeTranslation('general', 'date', 'Date')],
                    ['key' => 'amount', 'label' => $spreadsheetExportService->localeTranslation('general', 'amount', 'Amount'), 'type' => 'money', 'align' => 'right'],
                    ['key' => 'currency', 'label' => $spreadsheetExportService->localeTranslation('admin', 'currency.currency', 'Currency')],
                    ['key' => 'rate', 'label' => $spreadsheetExportService->localeTranslation('general', 'rate', 'Rate'), 'type' => 'money', 'align' => 'right'],
                    ['key' => 'payment_mode', 'label' => $spreadsheetExportService->localeTranslation('general', 'payment_method', 'Payment Method')],
                    ['key' => 'description', 'label' => $spreadsheetExportService->localeTranslation('general', 'description', 'Description')],
                ],
                default => [
                    ['key' => 'number', 'label' => $spreadsheetExportService->localeTranslation('general', 'number', 'Number')],
                    ['key' => 'date', 'label' => $spreadsheetExportService->localeTranslation('general', 'date', 'Date')],
                    ['key' => 'type', 'label' => $spreadsheetExportService->localeTranslation('general', 'type', 'Type')],
                    ['key' => 'amount', 'label' => $spreadsheetExportService->localeTranslation('general', 'amount', 'Amount'), 'type' => 'money', 'align' => 'right'],
                    ['key' => 'status', 'label' => $spreadsheetExportService->localeTranslation('general', 'status', 'Status')],
                    ['key' => 'description', 'label' => $spreadsheetExportService->localeTranslation('general', 'description', 'Description')],
                ],
            },
            'rows' => $rows,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ledger $customer)
    {
        $customer->load(['currency', 'opening', 'opening.transaction.currency','opening.transaction.lines']);
        return inertia('Ledgers/Customers/Edit', [
            'customer' => new LedgerResource($customer),
        ]);
    }

    protected function exportSaleRows(Ledger $customer): array
    {
        $sales = $customer->sales()->with(['transaction.currency', 'items'])->orderBy('date')->orderBy('id')->get();
        $rows = collect(SaleResource::collection($sales)->resolve());

        return $rows->map(function (array $row) {
            return [
                'number' => $row['number'] ?? $row['reference_id'] ?? $row['id'] ?? '-',
                'date' => $row['date'] ?? '-',
                'type' => $row['type'] ?? '-',
                'amount' => $row['amount'] ?? 0,
                'status' => $row['payment_status_label'] ?? $row['payment_status'] ?? '-',
                'description' => $row['description'] ?? '-',
            ];
        })->all();
    }

    protected function exportReceiptRows(Ledger $customer): array
    {
        $receipts = $customer->receipts()->with(['transaction.currency', 'transaction.lines.account'])->orderBy('date')->orderBy('id')->get();
        $rows = collect(ReceiptResource::collection($receipts)->resolve());

        return $rows->map(function (array $row) {
            return [
                'number' => $row['number'] ?? $row['reference_id'] ?? $row['id'] ?? '-',
                'date' => $row['date'] ?? '-',
                'amount' => $row['amount'] ?? 0,
                'currency' => $row['currency_code'] ?? data_get($row, 'transaction.currency.code') ?? data_get($row, 'transaction.currency.name') ?? '',
                'rate' => $row['rate'] ?? 0,
                'payment_mode' => $row['payment_mode_label'] ?? $row['payment_mode'] ?? '-',
                'description' => $row['narration'] ?? $row['description'] ?? '-',
            ];
        })->all();
    }

    protected function exportPaymentRows(Ledger $customer): array
    {
        $payments = $customer->payments()->with(['transaction.currency', 'transaction.lines.account'])->orderBy('date')->orderBy('id')->get();
        $rows = collect(PaymentResource::collection($payments)->resolve());

        return $rows->map(function (array $row) {
            return [
                'number' => $row['number'] ?? $row['reference_id'] ?? $row['id'] ?? '-',
                'date' => $row['date'] ?? '-',
                'amount' => $row['amount'] ?? 0,
                'currency' => $row['currency_code'] ?? data_get($row, 'transaction.currency.code') ?? data_get($row, 'transaction.currency.name') ?? '',
                'rate' => $row['rate'] ?? 0,
                'payment_mode' => $row['payment_mode_label'] ?? $row['payment_mode'] ?? '-',
                'description' => $row['narration'] ?? $row['description'] ?? '-',
            ];
        })->all();
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

    /**
     * Update the specified resource in storage.
     */
    public function update(LedgerUpdateRequest $request, Ledger $customer)
    {
        $validated = $request->validated();
        $customer->update($validated);

        // Remove existing opening balances

        if($customer->opening) {
            TransactionLine::where('transaction_id',$customer->opening->transaction_id)->forceDelete();
            $customer->opening->forceDelete();
            $customer->opening->transaction()->forceDelete();
        }


        if ($validated['amount'] && $validated['amount'] > 0 && $validated['opening_currency_id'] && $validated['rate']) {  // Update existing opening balances
            $glAccounts = Cache::get('gl_accounts');
            $arId = $glAccounts['account-receivable'];
            $equityId = $glAccounts['opening-balance-equity'];
            $transactionService = app(TransactionService::class);
            abort_unless($arId && $equityId, 500, 'System accounts (AR/AP) are missing.');

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $validated['opening_currency_id'],
                    'rate' => (float) $validated['rate'],
                    'date' => Carbon::now()->toDateString(),
                    'reference_type' => Ledger::class,
                    'reference_id' => $customer->id,
                    'remark' => 'Opening balance for customer ' . $customer->name,
                ],
                lines: [
                ['account_id' => $arId, 'ledger_id' => $customer->id, 'debit' => (float) $validated['amount'], 'credit' => 0, 'remark' => 'Opening balance for customer ' . $customer->name],
                ['account_id' => $equityId, 'debit' => 0, 'credit' => (float) $validated['amount'], 'remark' => 'Opening balance for customer ' . $customer->name],
            ]);

            $transaction->opening()->create([
                'ledgerable_id' => $customer->id,
                'ledgerable_type' => 'ledger',
            ]);
        }

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return to_route('customers.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.customer')]));
    }

    /**
     * Remove the specified resource from storage.
     */


    public function destroy(Request $request, Ledger $customer)
    {
        $openingTransactionId = $customer->opening?->transaction_id;

        // Allow delete only when customer has no transactions OR only opening transaction.
        $hasNonOpeningTransactions = TransactionLine::query()
            ->where('ledger_id', $customer->id)
            ->when(
                $openingTransactionId,
                fn ($q) => $q->where('transaction_id', '!=', $openingTransactionId),
                fn ($q) => $q // no opening found -> any transaction means blocked
            )
            ->exists();

        if ($hasNonOpeningTransactions) {
            return back()->with('error', __('Cannot delete customer: this customer has transactions. Please remove related transactions first.'));
        }

        DB::transaction(function () use ($customer, $openingTransactionId) {
            if ($openingTransactionId) {
                // Delete the whole opening transaction (both lines) and the opening record.
                TransactionLine::where('transaction_id', $openingTransactionId)->delete();
                Transaction::where('id', $openingTransactionId)->delete();
                $customer->opening()->delete();
            }

            $customer->delete();
        });

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()
            ->route('customers.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.customer')]));
    }

    public function restore(Request $request, Ledger $customer)
    {
        $opening = $customer->opening()->withTrashed()->first();
        $openingTransactionId = $opening?->transaction_id;

        DB::transaction(function () use ($customer, $openingTransactionId) {
            if ($openingTransactionId) {
                Transaction::withTrashed()->where('id', $openingTransactionId)->restore();
                TransactionLine::withTrashed()->where('transaction_id', $openingTransactionId)->restore();
                $customer->opening()->withTrashed()->restore();
            }

            $customer->restore();
        });

        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'ledgers'));

        return redirect()->route('customers.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.customer')]));
    }
}

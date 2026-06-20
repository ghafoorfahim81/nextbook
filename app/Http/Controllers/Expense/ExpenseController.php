<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\ExpenseStoreRequest;
use App\Http\Requests\Expense\ExpenseUpdateRequest;
use App\Http\Resources\Expense\ExpenseCategoryResource;
use App\Http\Resources\Expense\ExpenseResource;
use App\Models\Account\Account;
use App\Models\Administration\Currency;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseCategory;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Services\DateConversionService;
use App\Http\Resources\Account\AccountResource;
use App\Services\ActivityLogService;
use App\Enums\TransactionStatus;

class ExpenseController extends Controller
{
    private $dateConversionService;
    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(Expense::class, 'expense');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $expenses = Expense::with(['category', 'details'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Expenses/Expenses/Index', [
            'expenses' => ExpenseResource::collection($expenses),
            'filterOptions' => [
                'categories' => ExpenseCategoryResource::collection(ExpenseCategory::orderBy('name')->get(['id', 'name'])),
                'expenseAccounts' => AccountResource::collection(Account::whereHas('accountType', fn ($q) => $q->where('slug', 'expense'))
                    ->orderBy('name')
                    ->get()),
                'bankAccounts' => AccountResource::collection(Account::whereHas('accountType', fn($q) =>
                    $q->whereIn('slug', ['cash-or-bank'])
                )->get()),
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

    public function create(Request $request)
    {
        $latestNumber = (string) ((int) Expense::max('number') + 1);
        return inertia('Expenses/Expenses/Create', [
            'latestNumber' => $latestNumber,
            'categories' => ExpenseCategoryResource::collection(
                ExpenseCategory::where('is_active', true)->get()
            ),
            'expenseAccounts' => AccountResource::collection(Account::whereHas('accountType', fn($q) =>
                $q->where('slug', 'expense')
                )->get()),
            'bankAccounts' => AccountResource::collection(Account::whereHas('accountType', fn($q) =>
                $q->whereIn('slug', ['cash-or-bank'])
            )->get()),
        ]);
    }

    public function store(
        ExpenseStoreRequest $request,
        TransactionService $transactionService,
        ActivityLogService $activityLogService
    )
    {
        $expense = DB::transaction(function () use ($request, $transactionService, $activityLogService) {
            $validated = $request->validated();

            // Handle file upload
            // if ($request->hasFile('attachment')) {
            //     $validated['attachment'] = $request->file('attachment')
            //         ->store('expenses', 'public');
            // }
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null;
            $postImmediately = (bool) user_preference('transaction.expense_post_immediately', true);
            $documentStatus = $postImmediately ? TransactionStatus::POSTED->value : TransactionStatus::DRAFT->value;

            // Create expense record
            $expense = Expense::create([
                'number' => $validated['number'] ?? (string) ((int) Expense::max('number') + 1),
                'date' => $date,
                'remarks' => $validated['remarks'] ?? null,
                'category_id' => $validated['category_id'],
                'status' => $documentStatus,
            ]);

            // Create expense details
            $expense->details()->createMany($validated['details']);
            // Calculate total
            $total = collect($validated['details'])->sum('amount');

            // Create accounting transactions
            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $validated['currency_id'],
                    'rate' => $validated['rate'] ?? 1,
                    'date' => $date,
                    'reference_type' => Expense::class,
                    'reference_id' => $expense->id,
                    'remark' => 'Expense: ' . $expense->category->name . ' - ' . $expense->remarks,
                    'status' => $documentStatus,
                ],
                lines: [
                    [
                        'account_id' => $validated['expense_account_id'],
                        'debit' => $total,
                        'credit' => 0,
                        'remark' => 'Expense for ' . ' ' . $expense->category->name . ' - ' . $expense->remarks,
                        'remark_fa' => 'مصرف بابت' . ' ' . $expense->category->name . ' - ' . $expense->remarks,
                        'remark_ps' => 'د'. ' '. $expense->category->name.' '.'  مصرف ',
                    ],
                    [
                        'account_id' => $validated['bank_account_id'],
                        'debit' => 0,
                        'credit' => $total,
                        'remark' => 'Expense: ' . ' ' . $expense->category->name . ' - ' . $expense->remarks,
                        'remark_fa' => 'مصرف بابت' . ' ' . $expense->category->name . ' - ' . $expense->remarks,
                        'remark_ps' => 'د'. ' '. $expense->category->name.' '.' مصرف ',
                    ],
                ],
            );

            $activityLogService->logCreate(
                reference: $expense,
                module: 'expense',
                description: "Expense #{$expense->id} created.",
                newValues: [
                    'date' => $expense->date?->toDateString(),
                    'category_id' => $expense->category_id,
                    'remarks' => $expense->remarks,
                    'detail_count' => count($validated['details']),
                    'total' => (float) $total,
                    'currency_id' => $validated['currency_id'],
                    'rate' => (float) ($validated['rate'] ?? 1),
                ],
                metadata: [
                    'action' => 'expense_store',
                    'transaction_id' => $transaction->id,
                ],
            );


            return $expense;
        });

        if ($request->boolean('create_and_new')) {
            return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('general.resource.expense')]));
        }

        return redirect()->route('expenses.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.expense')]));
    }

    public function show(Request $request, Expense $expense)
    {
        $expense->load([
            'category',
            'details',
            'transaction.currency',
            'transaction.lines.account',
            'transaction.originalTransaction',
            'transaction.reversalTransaction',
        ]);

        return response()->json([
            'data' => new ExpenseResource($expense),
        ]);
    }

    public function post(Expense $expense, TransactionService $transactionService)
    {
        $this->authorize('update', $expense);

        if ($expense->status !== TransactionStatus::DRAFT->value) {
            abort(422, 'Only draft documents can be posted.');
        }

        DB::transaction(function () use ($expense, $transactionService) {
            $transactionService->postDraft($expense->transaction()->firstOrFail());
            $expense->update(['status' => TransactionStatus::POSTED->value]);
        });

        return back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.expense')]));
    }

    public function reverse(Request $request, Expense $expense, TransactionService $transactionService)
    {
        $this->authorize('update', $expense);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($expense->status !== TransactionStatus::POSTED->value) {
            abort(422, 'Only posted documents can be reversed.');
        }

        DB::transaction(function () use ($expense, $transactionService, $validated) {
            $transactionService->reverse($expense->transaction()->firstOrFail(), $validated['reason'], $expense?->number, Expense::class);
            $expense->update(['status' => TransactionStatus::REVERSED->value]);
        });

        return back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.expense')]));
    }

    public function edit(Request $request, Expense $expense)
    {
        $expense->load(['category', 'details', 'transaction.currency', 'transaction.lines.account']);

        // dd($expense);
        return inertia('Expenses/Expenses/Edit', [
            'expense' => new ExpenseResource($expense),
            'categories' => ExpenseCategoryResource::collection(
                ExpenseCategory::where('is_active', true)->get()
            ),
           'expenseAccounts' => AccountResource::collection(Account::whereHas('accountType', fn($q) =>
                $q->where('slug', 'expense')
            )->get()),
            'bankAccounts' => AccountResource::collection(Account::whereHas('accountType', fn($q) =>
                $q->whereIn('slug', ['cash-or-bank'])
            )->get()),
        ]);
    }

    public function update(
        ExpenseUpdateRequest $request,
        Expense $expense,
        TransactionService $transactionService,
        ActivityLogService $activityLogService
    )
    {
        if ($expense->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $beforeState = [
            'date' => $expense->date?->toDateString(),
            'category_id' => $expense->category_id,
            'remarks' => $expense->remarks,
            'detail_count' => $expense->details()->count(),
            'total' => (float) $expense->details()->sum('amount'),
            'currency_id' => $expense->transaction?->currency_id,
            'rate' => $expense->transaction?->rate,
        ];

        DB::transaction(function () use ($request, $expense, $transactionService, $activityLogService, $beforeState) {
            $validated = $request->validated();
            // Handle file upload
            // if ($request->hasFile('attachment')) {
            //     // Delete old attachment
            //     if ($expense->attachment) {
            //         Storage::disk('public')->delete($expense->attachment);
            //     }
            //     $validated['attachment'] = $request->file('attachment')
            //         ->store('expenses', 'public');
            // }

            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $expense->date;
            // Update expense record
            $expense->update([
                'number' => $validated['number'] ?? $expense->number,
                'date' => $date,
                'remarks' => $validated['remarks'] ?? null,
                'category_id' => $validated['category_id'],
                'status' => TransactionStatus::DRAFT->value,
            ]);

            // Update details
            $expense->details()->delete();
            $expense->details()->createMany($validated['details']);

            // Calculate total
            $total = $expense->details()->sum('amount');

            // Store old transaction IDs before nulling them
            $oldTransaction = $expense->transaction;
            $oldTransaction->lines()->forceDelete();
            $oldTransaction->forceDelete();

            // Create new transaction
            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $validated['currency_id'],
                    'rate' => $validated['rate'] ?? 1,
                    'date' => $date,
                    'reference_type' => Expense::class,
                    'reference_id' => $expense->id,
                    'remark' => 'Expense for ' . ' ' . $expense->category->name . ' - ' . $expense->remarks,
                    'status' => TransactionStatus::DRAFT->value,
                ],
                lines: [
                    [
                        'account_id' => $validated['expense_account_id'],
                        'debit' => $total,
                        'credit' => 0,
                        'remark' => 'Expense for ' . ' ' . $expense->category->name . ' - ' . $expense->remarks,
                        'remark_fa' => 'مصرف بابت' . ' ' . $expense->category->name . ' - ' . $expense->remarks,
                        'remark_ps' => 'د'. ' '. $expense->category->name.' '.'  مصرف ',
                    ],
                    [
                        'account_id' => $validated['bank_account_id'],
                        'debit' => 0,
                        'credit' => $total,
                        'remark' => 'Expense for ' . ' ' . $expense->category->name . ' - ' . $expense->remarks,
                        'remark_fa' => 'مصرف بابت' . ' ' . $expense->category->name . ' - ' . $expense->remarks,
                        'remark_ps' => 'د'. ' '. $expense->category->name.' '.' مصرف ',
                    ],
                ],
            );

            $activityLogService->logUpdate(
                reference: $expense,
                before: $beforeState,
                after: [
                    'date' => $expense->date?->toDateString(),
                    'category_id' => $expense->category_id,
                    'remarks' => $expense->remarks,
                    'detail_count' => count($validated['details']),
                    'total' => (float) $total,
                    'currency_id' => $validated['currency_id'],
                    'rate' => (float) ($validated['rate'] ?? 1),
                ],
                module: 'expense',
                description: "Expense #{$expense->id} updated.",
                metadata: [
                    'action' => 'expense_update',
                    'transaction_id' => $transaction->id,
                ],
            );

        });

        return redirect()->route('expenses.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.expense')]));
    }
    public function destroy(Request $request, Expense $expense, ActivityLogService $activityLogService)
    {
        if ($expense->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be deleted.');
        }

        $oldValues = [
            'date' => $expense->date?->toDateString(),
            'category_id' => $expense->category_id,
            'remarks' => $expense->remarks,
            'detail_count' => $expense->details()->count(),
            'total' => (float) $expense->details()->sum('amount'),
            'currency_id' => $expense->transaction?->currency_id,
            'rate' => $expense->transaction?->rate,
        ];

        DB::transaction(function () use ($expense) {
            // Soft delete related transactions
            if ($expense->transaction) {
                $expense->transaction->lines()->delete();
                $expense->transaction->delete();
            }

            // Soft delete details (if soft deletes enabled) or they cascade
            $expense->details()->delete();
            $expense->delete();
        });

        $activityLogService->logDelete(
            reference: $expense,
            module: 'expense',
            description: "Expense #{$expense->id} deleted.",
            oldValues: $oldValues,
            metadata: [
                'action' => 'expense_delete',
            ],
        );

        return redirect()->route('expenses.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.expense')]));
    }

    public function restore(Request $request, Expense $expense, ActivityLogService $activityLogService)
    {
        DB::transaction(function () use ($expense) {
            $expense->restore();
            $expense->details()->withTrashed()->restore();

            $transaction = $expense->transaction()->withTrashed()->first();
            if ($transaction) {
                $transaction->restore();
                $transaction->lines()->withTrashed()->restore();
            }
        });

        $activityLogService->logAction(
            eventType: 'restored',
            reference: $expense,
            module: 'expense',
            description: "Expense #{$expense->id} restored.",
            newValues: [
                'date' => $expense->date?->toDateString(),
                'category_id' => $expense->category_id,
            ],
            metadata: [
                'action' => 'expense_restore',
            ],
        );

        return back()->with('success', __('general.restored_successfully', ['resource' => __('general.resource.expense')]));
    }

    public function forceDelete(Request $request, Expense $expense)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('expenses', (string) $expense->id);

        return redirect()->route('expenses.index')
            ->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.expense')]));
    }

    public function export(Request $request, \App\Services\SpreadsheetExportService $exporter)
    {
        $this->authorize('viewAny', Expense::class);

        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $expenses = Expense::with(['category', 'details', 'transaction.lines.account'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->get();

        $rtl = in_array(app()->getLocale(), ['fa', 'ps'], true);
        $company = $request->user()?->company;
        $companyName = match (app()->getLocale()) {
            'fa'    => $company?->name_fa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            'ps'    => $company?->name_pa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            default => $company?->name_en ?: $company?->abbreviation ?: $company?->name_fa ?: $company?->name_pa ?: config('app.name'),
        };
        $t = fn (string $group, string $key, string $fallback = '') => $exporter->localeTranslation($group, $key, $fallback);

        $rows = $expenses->map(fn ($e) => [
            'date'             => $e->date ? $this->dateConversionService->toDisplay($e->date) : '-',
            'category'         => $e->category?->name ?? '-',
            'expense_account'  => $e->transaction?->lines?->firstWhere(fn ($l) => $l->debit > 0)?->account?->name ?? '-',
            'bank_account'     => $e->transaction?->lines?->firstWhere(fn ($l) => $l->credit > 0)?->account?->name ?? '-',
            'total'            => (float) ($e->details?->sum('amount') ?? 0),
            'remarks'          => $e->remarks ?? '-',
        ])->all();

        $label = $t('expense', 'expenses', 'Expenses');

        return $exporter->download([
            'filename'           => 'expenses-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name'         => $label,
            'sheet_title'        => $label,
            'title'              => $label,
            'company_name'       => $companyName,
            'exported_on'        => now()->format('Y m d'),
            'rtl'                => $rtl,
            'include_row_number' => true,
            'row_number_label'   => $t('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'date',            'label' => $t('general', 'date', 'Date'), 'width' => 14],
                ['key' => 'category',        'label' => $t('admin', 'category.category', 'Category'), 'width' => 18],
                ['key' => 'expense_account', 'label' => $t('expense', 'expense_account', 'Expense Account'), 'width' => 20],
                ['key' => 'bank_account',    'label' => $t('expense', 'bank_account', 'Bank/Cash Account'), 'width' => 20],
                ['key' => 'total',           'label' => $t('general', 'total', 'Total'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'remarks',         'label' => $t('general', 'remarks', 'Remarks'), 'width' => 24],
            ],
            'rows' => $rows,
        ]);
    }
}

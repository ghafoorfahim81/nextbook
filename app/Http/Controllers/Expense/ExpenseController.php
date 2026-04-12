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
                'categories' => ExpenseCategory::orderBy('name')->get(['id', 'name']),
                'expenseAccounts' => Account::whereHas('accountType', fn ($q) => $q->where('slug', 'expense'))
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'bankAccounts' => Account::whereHas('accountType', fn ($q) => $q->whereIn('slug', ['cash-or-bank']))
                    ->orderBy('name')
                    ->get(['id', 'name']),
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
        return inertia('Expenses/Expenses/Create', [
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

    public function store(ExpenseStoreRequest $request, TransactionService $transactionService)
    {
        $expense = DB::transaction(function () use ($request, $transactionService) {
            $validated = $request->validated();

            // Handle file upload
            // if ($request->hasFile('attachment')) {
            //     $validated['attachment'] = $request->file('attachment')
            //         ->store('expenses', 'public');
            // }
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null;

            // Create expense record
            $expense = Expense::create([
                'date' => $date,
                'remarks' => $validated['remarks'] ?? null,
                'category_id' => $validated['category_id'],
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
                ],
                lines: [
                    [
                        'account_id' => $validated['expense_account_id'],
                        'debit' => $total,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $validated['bank_account_id'],
                        'debit' => 0,
                        'credit' => $total,
                    ],
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
        ]);

        return response()->json([
            'data' => new ExpenseResource($expense),
        ]);
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

    public function update(ExpenseUpdateRequest $request, Expense $expense, TransactionService $transactionService)
    {

        DB::transaction(function () use ($request, $expense, $transactionService) {
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
                'date' => $date,
                'remarks' => $validated['remarks'] ?? null,
                'category_id' => $validated['category_id'],
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
                    'remark' => 'Expense: ' . $expense->category->name . ' - ' . $expense->remarks,
                ],
                lines: [
                    [
                        'account_id' => $validated['expense_account_id'],
                        'debit' => $total,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $validated['bank_account_id'],
                        'debit' => 0,
                        'credit' => $total,
                    ],
                ],
            );

        });

        return redirect()->route('expenses.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.expense')]));
    }
    public function destroy(Request $request, Expense $expense)
    {
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

        return redirect()->route('expenses.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.expense')]));
    }

    public function restore(Request $request, Expense $expense)
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

        return back()->with('success', __('general.restored_successfully', ['resource' => __('general.resource.expense')]));
    }

    public function forceDelete(Request $request, Expense $expense)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('expenses', (string) $expense->id);

        return redirect()->route('expenses.index')
            ->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.expense')]));
    }
}

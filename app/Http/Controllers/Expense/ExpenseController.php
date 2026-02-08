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

class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Expense::class, 'expense');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $expenses = Expense::with(['category', 'details'])
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Expenses/Expenses/Index', [
            'expenses' => ExpenseResource::collection($expenses),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Expenses/Expenses/Create', [
            'categories' => ExpenseCategoryResource::collection(
                ExpenseCategory::where('is_active', true)->get()
            ),
            'expenseAccounts' => Account::whereHas('accountType', fn($q) =>
                $q->where('slug', 'expense')
            )->get(),
            'bankAccounts' => Account::whereHas('accountType', fn($q) =>
                $q->whereIn('slug', ['cash-or-bank'])
            )->get(),
        ]);
    }

    public function store(ExpenseStoreRequest $request, TransactionService $transactionService)
    {
        $expense = DB::transaction(function () use ($request, $transactionService) {
            $validated = $request->validated();
            $dateService = app(\App\Services\DateConversionService::class);
            $validated['date'] = $dateService->toGregorian($validated['date']);

            // Handle file upload
            // if ($request->hasFile('attachment')) {
            //     $validated['attachment'] = $request->file('attachment')
            //         ->store('expenses', 'public');
            // }

            // Create expense record
            $expense = Expense::create([
                'date' => $validated['date'],
                'remarks' => $validated['remarks'] ?? null,
                'category_id' => $validated['category_id'],
            ]);

            // Create expense details
            $expense->details()->createMany($validated['details']);

            // Calculate total
            $total = collect($validated['details'])->sum('amount');

            // Create accounting transactions
            $transactions = $transactionService->createExpenseTransactions(
                $expense,
                $total,
                $validated['currency_id'],
                $validated['rate'] ?? 1,
                $validated['expense_account_id'],
                $validated['bank_account_id']
            );

            // Update expense with transaction IDs
            $expense->update([
                'expense_transaction_id' => $transactions['expense']->id,
                'bank_transaction_id' => $transactions['bank']->id,
            ]);

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
            'expenseTransaction.currency',
            'expenseTransaction.account',
            'bankTransaction.currency',
            'bankTransaction.account',
        ]);

        return response()->json([
            'data' => new ExpenseResource($expense),
        ]);
    }

    public function edit(Request $request, Expense $expense)
    {
        $expense->load(['category', 'details', 'expenseTransaction.currency', 'bankTransaction.currency', 'bankTransaction.account', 'bankTransaction.account']);

        // dd($expense);
        return inertia('Expenses/Expenses/Edit', [
            'expense' => new ExpenseResource($expense),
            'categories' => ExpenseCategoryResource::collection(
                ExpenseCategory::where('is_active', true)->get()
            ),
            'expenseAccounts' => Account::whereHas('accountType', fn($q) =>
                $q->where('slug', 'office-expenses')
            )->get(),
            'bankAccounts' => Account::whereHas('accountType', fn($q) =>
                $q->whereIn('slug', ['bank-account', 'cash','sarafi'])
            )->get(),
        ]);
    }

    public function update(ExpenseUpdateRequest $request, Expense $expense, TransactionService $transactionService)
    {

        DB::transaction(function () use ($request, $expense, $transactionService) {
            $validated = $request->validated();
            $dateService = app(abstract: \App\Services\DateConversionService::class);

            // Convert date if needed
            $validated['date'] = $dateService->toGregorian($validated['date']);

            // Handle file upload
            // if ($request->hasFile('attachment')) {
            //     // Delete old attachment
            //     if ($expense->attachment) {
            //         Storage::disk('public')->delete($expense->attachment);
            //     }
            //     $validated['attachment'] = $request->file('attachment')
            //         ->store('expenses', 'public');
            // }

            // Update expense record
            $expense->update([
                'date' => $validated['date'],
                'remarks' => $validated['remarks'] ?? null,
                'category_id' => $validated['category_id'],
            ]);

            // Update details
            $expense->details()->delete();
            $expense->details()->createMany($validated['details']);

            // Calculate total
            $total = $expense->details()->sum('amount');

            // Store old transaction IDs before nulling them
            $oldExpenseTransactionId = $expense->expense_transaction_id;
            $oldBankTransactionId = $expense->bank_transaction_id;

            // Null out transaction IDs first to avoid foreign key constraint issues
            $expense->update([
                'expense_transaction_id' => null,
                'bank_transaction_id' => null,
            ]);

            // Delete old transactions using stored IDs
            if ($oldExpenseTransactionId) {
                \App\Models\Transaction\Transaction::find($oldExpenseTransactionId)?->forceDelete();
            }
            if ($oldBankTransactionId) {
                \App\Models\Transaction\Transaction::find($oldBankTransactionId)?->forceDelete();
            }

            // Create new transactions
            $transactions = $transactionService->createExpenseTransactions(
                $expense->fresh(),
                $total,
                $validated['currency_id'],
                $validated['rate'],
                $validated['expense_account_id'],
                $validated['bank_account_id']
            );

            // Update expense with new transaction IDs
            $expense->update([
                'expense_transaction_id' => $transactions['expense']->id,
                'bank_transaction_id' => $transactions['bank']->id,
            ]);
        });

        return redirect()->route('expenses.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.expense')]));
    }
    public function destroy(Request $request, Expense $expense)
    {
        DB::transaction(function () use ($expense) {
            // Soft delete related transactions
            if ($expense->expenseTransaction) {
                $expense->expenseTransaction->delete();
            }
            if ($expense->bankTransaction) {
                $expense->bankTransaction->delete();
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
            $expense->details()->restore();
            // Restore transactions
            if ($expense->expense_transaction_id) {
                \App\Models\Transaction\Transaction::withTrashed()
                    ->find($expense->expense_transaction_id)?->restore();
            }
            if ($expense->bank_transaction_id) {
                \App\Models\Transaction\Transaction::withTrashed()
                    ->find($expense->bank_transaction_id)?->restore();
            }
        });

        return back()->with('success', __('general.restored_successfully', ['resource' => __('general.resource.expense')]));
    }
}


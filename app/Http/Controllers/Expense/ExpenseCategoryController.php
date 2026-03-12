<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\ExpenseCategoryStoreRequest;
use App\Http\Requests\Expense\ExpenseCategoryUpdateRequest;
use App\Http\Resources\Expense\ExpenseCategoryResource;
use App\Models\Expense\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ExpenseCategory::class, 'expense_category');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $categories = ExpenseCategory::query()
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Expenses/Categories/Index', [
            'categories' => ExpenseCategoryResource::collection($categories),
        ]);
    }

    public function store(ExpenseCategoryStoreRequest $request)
    {
        ExpenseCategory::create($request->validated());
        return redirect()->route('expense-categories.index')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.expense_category')]));
    }

    public function show(Request $request, ExpenseCategory $expenseCategory)
    {
        return response()->json([
            'data' => new ExpenseCategoryResource($expenseCategory),
        ]);
    }

    public function update(ExpenseCategoryUpdateRequest $request, ExpenseCategory $expenseCategory)
    {
        $expenseCategory->update($request->validated());
        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.expense_category')]));
    }

    public function destroy(Request $request, ExpenseCategory $expenseCategory)
    {
        if (!$expenseCategory->canBeDeleted()) {
            $message = $expenseCategory->getDependencyMessage() ?? 'Cannot delete: category has dependencies.';
            return redirect()->back()->with('error', $message);
        }

        $expenseCategory->delete();
        return redirect()->route('expense-categories.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.expense_category')]));
    }

    public function restore(Request $request, ExpenseCategory $expenseCategory)
    {
        $expenseCategory->restore();
        return back()->with('success', __('general.restored_successfully', ['resource' => __('general.resource.expense_category')]));
    }
}


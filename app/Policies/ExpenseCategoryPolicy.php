<?php

namespace App\Policies;

use App\Models\Expense\ExpenseCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpenseCategoryPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'expense_categories.view_any');
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $this->hasPermission($user, 'expense_categories.view')
            && $this->sameBranch($user, $category);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'expense_categories.create');
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $this->hasPermission($user, 'expense_categories.update')
            && $this->sameBranch($user, $category);
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $this->hasPermission($user, 'expense_categories.delete')
            && $this->sameBranch($user, $category);
    }
}



<?php

namespace App\Policies;

use App\Models\Expense\Expense;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpensePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->hasPermission($user, 'expenses.view')
            && $this->sameBranch($user, $expense);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'expenses.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->hasPermission($user, 'expenses.update')
            && $this->sameBranch($user, $expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->hasPermission($user, 'expenses.delete')
            && $this->sameBranch($user, $expense);
    }
}



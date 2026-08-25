<?php

namespace App\Policies\Hr;

use App\Models\Hr\EmployeeLoan;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeLoanPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'loans.view_any');
    }

    public function view(User $user, EmployeeLoan $employeeLoan): bool
    {
        return $this->hasPermission($user, 'loans.view')
            && $this->sameBranch($user, $employeeLoan);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'loans.create');
    }

    public function update(User $user, EmployeeLoan $employeeLoan): bool
    {
        return $this->hasPermission($user, 'loans.update')
            && $this->sameBranch($user, $employeeLoan);
    }

    public function delete(User $user, EmployeeLoan $employeeLoan): bool
    {
        return $this->hasPermission($user, 'loans.delete')
            && $this->sameBranch($user, $employeeLoan);
    }

    /**
     * Approving is a SEPARATE grant from editing. Preparing a document on
     * someone's behalf is not the same authority as agreeing to it, and
     * collapsing the two removes the only control on the amount.
     */
    public function approve(User $user, EmployeeLoan $employeeLoan): bool
    {
        return $this->hasPermission($user, 'loans.approve')
            && $this->sameBranch($user, $employeeLoan);
    }

    public function reject(User $user, EmployeeLoan $employeeLoan): bool
    {
        return $this->hasPermission($user, 'loans.reject')
            && $this->sameBranch($user, $employeeLoan);
    }

    public function print(User $user, EmployeeLoan $employeeLoan): bool
    {
        return $this->hasPermission($user, 'loans.print')
            && $this->sameBranch($user, $employeeLoan);
    }
}

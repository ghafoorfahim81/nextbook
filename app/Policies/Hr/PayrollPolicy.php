<?php

namespace App\Policies\Hr;

use App\Models\Hr\Payroll;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayrollPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'payrolls.view_any');
    }

    public function view(User $user, Payroll $payroll): bool
    {
        return $this->hasPermission($user, 'payrolls.view')
            && $this->sameBranch($user, $payroll);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'payrolls.create');
    }

    public function update(User $user, Payroll $payroll): bool
    {
        return $this->hasPermission($user, 'payrolls.update')
            && $this->sameBranch($user, $payroll);
    }

    public function delete(User $user, Payroll $payroll): bool
    {
        return $this->hasPermission($user, 'payrolls.delete')
            && $this->sameBranch($user, $payroll);
    }

    /**
     * Approving is a SEPARATE grant from editing. Preparing a document on
     * someone's behalf is not the same authority as agreeing to it, and
     * collapsing the two removes the only control on the amount.
     */
    public function approve(User $user, Payroll $payroll): bool
    {
        return $this->hasPermission($user, 'payrolls.approve')
            && $this->sameBranch($user, $payroll);
    }

    public function reject(User $user, Payroll $payroll): bool
    {
        return $this->hasPermission($user, 'payrolls.reject')
            && $this->sameBranch($user, $payroll);
    }

    public function print(User $user, Payroll $payroll): bool
    {
        return $this->hasPermission($user, 'payrolls.print')
            && $this->sameBranch($user, $payroll);
    }
}

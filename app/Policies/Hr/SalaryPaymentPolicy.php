<?php

namespace App\Policies\Hr;

use App\Models\Hr\SalaryPayment;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalaryPaymentPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'salary_payments.view_any');
    }

    public function view(User $user, SalaryPayment $salaryPayment): bool
    {
        return $this->hasPermission($user, 'salary_payments.view')
            && $this->sameBranch($user, $salaryPayment);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'salary_payments.create');
    }

    public function update(User $user, SalaryPayment $salaryPayment): bool
    {
        return $this->hasPermission($user, 'salary_payments.update')
            && $this->sameBranch($user, $salaryPayment);
    }

    public function delete(User $user, SalaryPayment $salaryPayment): bool
    {
        return $this->hasPermission($user, 'salary_payments.delete')
            && $this->sameBranch($user, $salaryPayment);
    }

    /**
     * Approving is a SEPARATE grant from editing. Preparing a document on
     * someone's behalf is not the same authority as agreeing to it, and
     * collapsing the two removes the only control on the amount.
     */
    public function approve(User $user, SalaryPayment $salaryPayment): bool
    {
        return $this->hasPermission($user, 'salary_payments.approve')
            && $this->sameBranch($user, $salaryPayment);
    }

    public function reject(User $user, SalaryPayment $salaryPayment): bool
    {
        return $this->hasPermission($user, 'salary_payments.reject')
            && $this->sameBranch($user, $salaryPayment);
    }

    public function print(User $user, SalaryPayment $salaryPayment): bool
    {
        return $this->hasPermission($user, 'salary_payments.print')
            && $this->sameBranch($user, $salaryPayment);
    }
}

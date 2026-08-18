<?php

namespace App\Policies\Hr;

use App\Models\Hr\EmployeeContract;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeContractPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'employee_contracts.view_any');
    }

    public function view(User $user, EmployeeContract $contract): bool
    {
        return $this->hasPermission($user, 'employee_contracts.view')
            && $this->sameBranch($user, $contract);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'employee_contracts.create');
    }

    public function update(User $user, EmployeeContract $contract): bool
    {
        return $this->hasPermission($user, 'employee_contracts.update')
            && $this->sameBranch($user, $contract);
    }

    public function delete(User $user, EmployeeContract $contract): bool
    {
        return $this->hasPermission($user, 'employee_contracts.delete')
            && $this->sameBranch($user, $contract);
    }
}

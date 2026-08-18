<?php

namespace App\Policies\Hr;

use App\Models\Hr\Employee;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'employees.view_any');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->hasPermission($user, 'employees.view')
            && $this->sameBranch($user, $employee);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->hasPermission($user, 'employees.update')
            && $this->sameBranch($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->hasPermission($user, 'employees.delete')
            && $this->sameBranch($user, $employee);
    }
}

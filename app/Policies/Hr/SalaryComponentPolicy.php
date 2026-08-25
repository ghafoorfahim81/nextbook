<?php

namespace App\Policies\Hr;

use App\Models\Hr\SalaryComponent;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalaryComponentPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'salary_components.view_any');
    }

    public function view(User $user, SalaryComponent $salaryComponent): bool
    {
        return $this->hasPermission($user, 'salary_components.view')
            && $this->sameBranch($user, $salaryComponent);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'salary_components.create');
    }

    public function update(User $user, SalaryComponent $salaryComponent): bool
    {
        return $this->hasPermission($user, 'salary_components.update')
            && $this->sameBranch($user, $salaryComponent);
    }

    public function delete(User $user, SalaryComponent $salaryComponent): bool
    {
        return $this->hasPermission($user, 'salary_components.delete')
            && $this->sameBranch($user, $salaryComponent);
    }
}

<?php

namespace App\Policies\Hr;

use App\Models\Hr\SalaryStructure;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalaryStructurePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'salary_structures.view_any');
    }

    public function view(User $user, SalaryStructure $salaryStructure): bool
    {
        return $this->hasPermission($user, 'salary_structures.view')
            && $this->sameBranch($user, $salaryStructure);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'salary_structures.create');
    }

    public function update(User $user, SalaryStructure $salaryStructure): bool
    {
        return $this->hasPermission($user, 'salary_structures.update')
            && $this->sameBranch($user, $salaryStructure);
    }

    public function delete(User $user, SalaryStructure $salaryStructure): bool
    {
        return $this->hasPermission($user, 'salary_structures.delete')
            && $this->sameBranch($user, $salaryStructure);
    }
}

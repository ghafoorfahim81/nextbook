<?php

namespace App\Policies;

use App\Models\Administration\Department;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DepartmentPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'departments.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $this->hasPermission($user, 'departments.view')
            && $this->sameBranch($user, $department);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'departments.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $this->hasPermission($user, 'departments.update')
            && $this->sameBranch($user, $department);
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->hasPermission($user, 'departments.delete')
            && $this->sameBranch($user, $department);
    }
}



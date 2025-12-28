<?php

namespace App\Policies;

use App\Models\Administration\Designation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DesignationPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'designations.view_any');
    }

    public function view(User $user, Designation $designation): bool
    {
        return $this->hasPermission($user, 'designations.view')
            && $this->sameBranch($user, $designation);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'designations.create');
    }

    public function update(User $user, Designation $designation): bool
    {
        return $this->hasPermission($user, 'designations.update')
            && $this->sameBranch($user, $designation);
    }

    public function delete(User $user, Designation $designation): bool
    {
        return $this->hasPermission($user, 'designations.delete')
            && $this->sameBranch($user, $designation);
    }
}



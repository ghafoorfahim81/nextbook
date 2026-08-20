<?php

namespace App\Policies\Hr;

use App\Models\Hr\LeaveAllocation;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeaveAllocationPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'leaves.view_any');
    }

    public function view(User $user, LeaveAllocation $allocation): bool
    {
        return $this->hasPermission($user, 'leaves.view')
            && $this->sameBranch($user, $allocation);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'leaves.create');
    }

    public function update(User $user, LeaveAllocation $allocation): bool
    {
        return $this->hasPermission($user, 'leaves.update')
            && $this->sameBranch($user, $allocation);
    }

    public function delete(User $user, LeaveAllocation $allocation): bool
    {
        return $this->hasPermission($user, 'leaves.delete')
            && $this->sameBranch($user, $allocation);
    }
}

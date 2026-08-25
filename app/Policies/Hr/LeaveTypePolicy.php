<?php

namespace App\Policies\Hr;

use App\Models\Hr\LeaveType;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeaveTypePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'leave_types.view_any');
    }

    public function view(User $user, LeaveType $leaveType): bool
    {
        return $this->hasPermission($user, 'leave_types.view')
            && $this->sameBranch($user, $leaveType);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'leave_types.create');
    }

    public function update(User $user, LeaveType $leaveType): bool
    {
        return $this->hasPermission($user, 'leave_types.update')
            && $this->sameBranch($user, $leaveType);
    }

    public function delete(User $user, LeaveType $leaveType): bool
    {
        return $this->hasPermission($user, 'leave_types.delete')
            && $this->sameBranch($user, $leaveType);
    }
}

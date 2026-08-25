<?php

namespace App\Policies\Hr;

use App\Models\Hr\LeaveRequest;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeaveRequestPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'leave_applications.view_any');
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->hasPermission($user, 'leave_applications.view')
            && $this->sameBranch($user, $leaveRequest);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'leave_applications.create');
    }

    public function update(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->hasPermission($user, 'leave_applications.update')
            && $this->sameBranch($user, $leaveRequest);
    }

    public function delete(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->hasPermission($user, 'leave_applications.delete')
            && $this->sameBranch($user, $leaveRequest);
    }

    /**
     * Approving is a distinct authority from editing.
     *
     * Someone who can raise a leave request on an employee's behalf should not
     * automatically be able to grant it, which is exactly what reusing
     * `update` here would allow.
     */
    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->hasPermission($user, 'leave_applications.approve')
            && $this->sameBranch($user, $leaveRequest);
    }

    public function reject(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->hasPermission($user, 'leave_applications.reject')
            && $this->sameBranch($user, $leaveRequest);
    }
}

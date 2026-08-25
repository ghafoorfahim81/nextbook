<?php

namespace App\Policies\Hr;

use App\Models\Hr\Attendance;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendancePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'attendances.view_any');
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $this->hasPermission($user, 'attendances.view')
            && $this->sameBranch($user, $attendance);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'attendances.create');
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $this->hasPermission($user, 'attendances.update')
            && $this->sameBranch($user, $attendance);
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $this->hasPermission($user, 'attendances.delete')
            && $this->sameBranch($user, $attendance);
    }
}

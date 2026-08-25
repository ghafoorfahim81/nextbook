<?php

namespace App\Policies\Hr;

use App\Models\Hr\AttendanceDevice;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendanceDevicePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'attendance_devices.view_any');
    }

    public function view(User $user, AttendanceDevice $device): bool
    {
        return $this->hasPermission($user, 'attendance_devices.view')
            && $this->sameBranch($user, $device);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'attendance_devices.create');
    }

    public function update(User $user, AttendanceDevice $device): bool
    {
        return $this->hasPermission($user, 'attendance_devices.update')
            && $this->sameBranch($user, $device);
    }

    public function delete(User $user, AttendanceDevice $device): bool
    {
        return $this->hasPermission($user, 'attendance_devices.delete')
            && $this->sameBranch($user, $device);
    }
}

<?php

namespace App\Policies\Hr;

use App\Models\Hr\Holiday;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class HolidayPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'holidays.view_any');
    }

    public function view(User $user, Holiday $holiday): bool
    {
        return $this->hasPermission($user, 'holidays.view')
            && $this->sameBranch($user, $holiday);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'holidays.create');
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $this->hasPermission($user, 'holidays.update')
            && $this->sameBranch($user, $holiday);
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return $this->hasPermission($user, 'holidays.delete')
            && $this->sameBranch($user, $holiday);
    }
}

<?php

namespace App\Policies\Hr;

use App\Models\Hr\Shift;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShiftPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'shifts.view_any');
    }

    public function view(User $user, Shift $shift): bool
    {
        return $this->hasPermission($user, 'shifts.view')
            && $this->sameBranch($user, $shift);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'shifts.create');
    }

    public function update(User $user, Shift $shift): bool
    {
        return $this->hasPermission($user, 'shifts.update')
            && $this->sameBranch($user, $shift);
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $this->hasPermission($user, 'shifts.delete')
            && $this->sameBranch($user, $shift);
    }
}

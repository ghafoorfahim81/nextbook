<?php

namespace App\Policies\Hr;

use App\Models\Hr\Interview;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class InterviewPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'interviews.view_any');
    }

    public function view(User $user, Interview $interview): bool
    {
        return $this->hasPermission($user, 'interviews.view')
            && $this->sameBranch($user, $interview);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'interviews.create');
    }

    public function update(User $user, Interview $interview): bool
    {
        return $this->hasPermission($user, 'interviews.update')
            && $this->sameBranch($user, $interview);
    }

    public function delete(User $user, Interview $interview): bool
    {
        return $this->hasPermission($user, 'interviews.delete')
            && $this->sameBranch($user, $interview);
    }
}

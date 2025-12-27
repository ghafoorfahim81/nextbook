<?php

namespace App\Policies;

use App\Models\Owner\Owner;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OwnerPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'owners.view');
    }

    public function view(User $user, Owner $owner): bool
    {
        return $this->hasPermission($user, 'owners.view')
            && $this->sameBranch($user, $owner);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'owners.create');
    }

    public function update(User $user, Owner $owner): bool
    {
        return $this->hasPermission($user, 'owners.update')
            && $this->sameBranch($user, $owner);
    }

    public function delete(User $user, Owner $owner): bool
    {
        return $this->hasPermission($user, 'owners.delete')
            && $this->sameBranch($user, $owner);
    }
}



<?php

namespace App\Policies;

use App\Models\Administration\Branch;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BranchPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, 'branches.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, 'branches.update');
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, 'branches.delete');
    }
}



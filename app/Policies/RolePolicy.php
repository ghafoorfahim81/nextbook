<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->hasPermission($user, 'roles.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->hasPermission($user, 'roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->hasPermission($user, 'roles.delete');
    }
}



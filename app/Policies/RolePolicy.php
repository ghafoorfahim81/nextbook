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
        return $this->hasPermission($user, 'roles.view_any');
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

    /**
     * `authorizeResource` only covers the seven RESTful actions, so restore
     * was reachable by any authenticated user. Putting a role back carries
     * the same authority as removing it, so it reuses `roles.delete`.
     */
    public function restore(User $user, Role $role): bool
    {
        return $this->hasPermission($user, 'roles.delete');
    }
}



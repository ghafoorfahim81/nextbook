<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $this->hasPermission($user, 'users.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $this->hasPermission($user, 'users.update');
    }

    public function delete(User $user, User $model): bool
    {
        // Prevent deleting self even if permission exists; controller also guards this.
        if ($user->id === $model->id) {
            return false;
        }

        return $this->hasPermission($user, 'users.delete');
    }
}



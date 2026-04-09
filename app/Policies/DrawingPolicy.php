<?php

namespace App\Policies;

use App\Models\Owner\Drawing;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DrawingPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'drawings.view_any');
    }

    public function view(User $user, Drawing $drawing): bool
    {
        return $this->hasPermission($user, 'drawings.view')
            && $this->sameBranch($user, $drawing);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'drawings.create');
    }

    public function update(User $user, Drawing $drawing): bool
    {
        return $this->hasPermission($user, 'drawings.update')
            && $this->sameBranch($user, $drawing);
    }

    public function delete(User $user, Drawing $drawing): bool
    {
        return $this->hasPermission($user, 'drawings.delete')
            && $this->sameBranch($user, $drawing);
    }
}

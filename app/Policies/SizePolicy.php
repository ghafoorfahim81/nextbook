<?php

namespace App\Policies;

use App\Models\Administration\Size;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SizePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'sizes.view');
    }

    public function view(User $user, Size $size): bool
    {
        return $this->hasPermission($user, 'sizes.view')
            && $this->sameBranch($user, $size);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'sizes.create');
    }

    public function update(User $user, Size $size): bool
    {
        return $this->hasPermission($user, 'sizes.update')
            && $this->sameBranch($user, $size);
    }

    public function delete(User $user, Size $size): bool
    {
        return $this->hasPermission($user, 'sizes.delete')
            && $this->sameBranch($user, $size);
    }
}



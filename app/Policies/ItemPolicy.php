<?php

namespace App\Policies;

use App\Models\Inventory\Item;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'items.view_any');
    }

    public function view(User $user, Item $item): bool
    {
        return $this->hasPermission($user, 'items.view')
            && $this->sameBranch($user, $item);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'items.create');
    }

    public function update(User $user, Item $item): bool
    {
        return $this->hasPermission($user, 'items.update')
            && $this->sameBranch($user, $item);
    }

    public function delete(User $user, Item $item): bool
    {
        return $this->hasPermission($user, 'items.delete')
            && $this->sameBranch($user, $item);
    }
}


